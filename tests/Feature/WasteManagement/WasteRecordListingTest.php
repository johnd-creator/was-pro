<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Services\OperationalAlerts;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->withHeader('X-Waspro-Records-Contract', '2');
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $this->role = Role::factory()->create();
    $this->role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_all', 'waste_records.view_own'])->pluck('id'));
    $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($this->user);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->type = WasteType::factory()->create(['name' => 'Sludge Neutral', 'code' => 'TYPE-NEUTRAL', 'storage_period_days' => 365]);
});

afterEach(function () {
    $this->tenants->switchToPublic();
    $this->travelBack();
});

function listingRecord($test, array $attributes = [], ?string $expiry = '2027-01-01'): WasteRecord
{
    $test->tenants->switchToSchema($test->organization->schema_name);
    $record = WasteRecord::factory()->approved()->create([
        'date' => '2026-10-01', 'waste_type_id' => $test->type->id, 'quantity' => 100, 'unit' => 'kg', 'source' => 'Neutral', 'created_by' => $test->user->id,
        ...$attributes,
    ]);
    $record->forceFill(['expiry_date' => $expiry])->saveQuietly();

    return $record;
}

function listingUrl($test, array $parameters = []): string
{
    return route('waste-management.records.index', ['organization_id' => $test->organization->id, 'month' => '2026-11', ...$parameters]);
}

function listingInertiaHeaders(array $headers = []): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ...$headers,
    ];
}
test('record listing paginates on the server and preserves the unfiltered summary', function () {
    for ($i = 1; $i <= 23; $i++) {
        listingRecord($this, ['record_number' => sprintf('PAGED-%03d', $i), 'quantity' => $i]);
    }
    $this->get(listingUrl($this, ['sort' => 'quantity_low']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords.data', 10)->where('wasteRecords.total', 23)->where('wasteRecords.current_page', 1)
        ->where('wasteRecords.last_page', 3)->where('wasteRecords.per_page', 10)->where('wasteRecords.data.0.record_number', 'PAGED-001')
        ->where('recordSummary.total', 23));
    $this->get(listingUrl($this, ['sort' => 'quantity_low', 'page' => 3]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords.data', 3)->where('wasteRecords.data.0.record_number', 'PAGED-021')->where('recordSummary.total', 23));
    $this->get(listingUrl($this, ['page' => 4]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords.data', 0)->where('wasteRecords.total', 23));
    $this->get(listingUrl($this, ['search' => 'pAgEd-023']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords.data', 1)->where('wasteRecords.total', 1)->where('wasteRecords.data.0.record_number', 'PAGED-023')->where('recordSummary.total', 23));
});

test('record listing applies all supported sort orders with deterministic ties', function () {
    $old = listingRecord($this, ['date' => '2026-10-01', 'quantity' => 90]);
    $middle = listingRecord($this, ['date' => '2026-10-03', 'quantity' => 10]);
    $new = listingRecord($this, ['date' => '2026-10-05', 'quantity' => 50]);
    foreach (['latest' => [$new, $middle, $old], 'oldest' => [$old, $middle, $new], 'quantity_high' => [$old, $new, $middle], 'quantity_low' => [$middle, $new, $old]] as $sort => $records) {
        $this->get(listingUrl($this, ['sort' => $sort]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteRecords.data', fn ($rows): bool => collect($rows)->pluck('id')->all() === array_column($records, 'id'))
            ->where('filters.sort', $sort));
    }
    $tied = collect(range(1, 12))->map(fn () => listingRecord($this, ['date' => '2026-10-10', 'quantity' => 7]))->sortBy('id')->pluck('id')->values();
    $first = $this->getJson(listingUrl($this, ['sort' => 'quantity_low']), listingInertiaHeaders())->assertOk()->json('props.wasteRecords.data');
    $second = $this->getJson(listingUrl($this, ['sort' => 'quantity_low', 'page' => 2]), listingInertiaHeaders())->assertOk()->json('props.wasteRecords.data');
    expect(collect($first)->pluck('id')->merge(collect($second)->pluck('id'))->take(12)->values()->all())->toBe($tied->all());
});

test('record search includes record number waste names codes and both source formats', function () {
    $number = listingRecord($this, ['record_number' => 'UNIQUE-DOCUMENT']);
    $source = listingRecord($this, ['source' => 'Unique legacy location']);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $type = WasteType::factory()->create(['name' => 'Unique Material Name', 'code' => 'UNIQUE-TYPE-CODE']);
    $typed = listingRecord($this, ['waste_type_id' => $type->id]);
    $structured = WasteSource::query()->create(['name' => 'Unique Structured Location', 'is_active' => true]);
    $structuredRecord = listingRecord($this, ['source_id' => $structured->id]);
    listingRecord($this);
    foreach (['unique-document' => $number, 'LEGACY LOCATION' => $source, 'material name' => $typed, 'unique-type-code' => $typed, 'structured location' => $structuredRecord] as $search => $record) {
        $this->get(listingUrl($this, ['search' => $search]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteRecords.total', 1)->where('wasteRecords.data.0.id', $record->id)->where('recordSummary.total', 5));
    }
});

test('record attention and summaries match legacy snapshot calculations including future and deleted haulings', function () {
    $expired = listingRecord($this, [], '2026-11-11');
    listingRecord($this, [], '2026-11-12');
    listingRecord($this, [], '2026-11-19');
    listingRecord($this, [], '2026-11-20');
    listingRecord($this, [], null);
    $completed = listingRecord($this, [], '2026-11-01');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $completed->id, 'hauling_date' => '2026-11-05', 'quantity' => 100, 'unit' => 'kg']);
    $futureHauling = listingRecord($this, [], '2026-11-01');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $futureHauling->id, 'hauling_date' => '2026-11-20', 'quantity' => 100, 'unit' => 'kg']);
    $deletedHauling = WasteHauling::factory()->approved()->create(['waste_record_id' => $expired->id, 'hauling_date' => '2026-11-05', 'quantity' => 100, 'unit' => 'kg']);
    WasteHauling::query()->whereKey($deletedHauling->id)->update(['deleted_at' => now()]);
    listingRecord($this, ['status' => 'pending_review', 'date' => '2026-11-01']);
    listingRecord($this, ['status' => 'pending_review', 'date' => '2026-10-01']);
    listingRecord($this, ['status' => 'draft', 'date' => '2026-11-01']);
    listingRecord($this, ['date' => '2026-11-20'], '2026-11-21');
    $deleted = listingRecord($this, [], '2026-11-01');
    WasteRecord::query()->whereKey($deleted->id)->update(['deleted_at' => now()]);
    $alerts = app(OperationalAlerts::class);
    $snapshot = $alerts->snapshot('2026-11');
    $all = WasteRecord::query()->whereDate('date', '<=', $snapshot)->with(['haulings' => fn ($query) => $query->whereDate('hauling_date', '<=', $snapshot)])->get();
    foreach (['expired', 'expiring_soon', 'critical', 'backlog', 'pending', 'fresh', 'completed'] as $attention) {
        $expected = $all->filter(fn (WasteRecord $record): bool => $alerts->matchesAttention($record, $attention, $snapshot))->pluck('id')->sort()->values()->all();
        $this->get(listingUrl($this, ['attention' => $attention]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteRecords.data', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === $expected)
            ->where('wasteRecords.total', count($expected))->where('recordSummary.total', 10)->where('recordSummary.draft', 1)
            ->where('recordSummary.pending', 1)->where('recordSummary.expired', 2)->where('recordSummary.expiring_soon', 2));
    }
    $this->get(listingUrl($this, ['search' => $futureHauling->record_number]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('wasteRecords.data.0.remaining_quantity', 100)->where('wasteRecords.data.0.available_quantity', 0));
    $this->get(listingUrl($this, ['month' => '2026-10', 'attention' => 'expired']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords.data', 0)->where('filters.as_of', '2026-10-31'));
});

test('partial record reload returns only requested page props and includes filtering metadata', function () {
    for ($i = 0; $i < 11; $i++) {
        listingRecord($this, ['source' => 'Partial source']);
    }
    $this->getJson(listingUrl($this, ['page' => 2, 'search' => 'Partial', 'sort' => 'oldest']), listingInertiaHeaders([
        'X-Inertia-Partial-Component' => 'waste-management/records/Index', 'X-Inertia-Partial-Data' => 'wasteRecords,filters,recordSummary',
    ]))->assertOk()->assertJsonCount(1, 'props.wasteRecords.data')->assertJsonPath('props.wasteRecords.total', 11)
        ->assertJsonPath('props.filters.search', 'Partial')->assertJsonPath('props.filters.sort', 'oldest')->assertJsonPath('props.recordSummary.total', 11)
        ->assertJsonMissingPath('props.wasteTypes')->assertJsonMissingPath('props.wasteSources');
});

test('record pagination search and summaries retain owner scope and deny other tenants', function () {
    listingRecord($this, ['record_number' => 'OWN-RECORD']);
    $this->tenants->switchToPublic();
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    $otherOrganization = Organization::factory()->create();
    $this->role->permissions()->sync(Permission::where('slug', 'waste_records.view_own')->pluck('id'));
    $this->user->unsetRelation('role');
    for ($i = 0; $i < 12; $i++) {
        listingRecord($this, ['created_by' => $other->id, 'record_number' => 'OTHER-'.$i]);
    }
    $this->get(listingUrl($this))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('wasteRecords.total', 1)->where('wasteRecords.data.0.record_number', 'OWN-RECORD')->where('recordSummary.total', 1));
    $this->get(listingUrl($this, ['search' => 'OTHER']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('wasteRecords.total', 0)->where('recordSummary.total', 1));
    $this->get(listingUrl($this, ['organization_id' => $otherOrganization->id]))->assertForbidden();
});

test('record listing validates pagination sort search and attention inputs', function () {
    foreach (['page' => 0, 'sort' => 'quantity desc; DROP TABLE waste_records', 'search' => str_repeat('a', 201), 'attention' => 'invalid', 'month' => '2026-99'] as $field => $value) {
        $this->getJson(listingUrl($this, [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('record search treats SQL wildcard characters as literal user text', function () {
    $percent = listingRecord($this, ['source' => '100% material']);
    $underscore = listingRecord($this, ['source' => 'area_special']);
    $backslash = listingRecord($this, ['source' => 'area\\special']);
    listingRecord($this, ['source' => 'Ordinary material']);
    foreach (['%' => $percent, '_' => $underscore, '\\' => $backslash] as $search => $record) {
        $this->get(listingUrl($this, ['search' => $search]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteRecords.total', 1)->where('wasteRecords.data.0.id', $record->id));
    }
});

test('record page query count stays bounded as tenant history grows', function () {
    for ($i = 0; $i < 10; $i++) {
        listingRecord($this);
    }
    $this->get(listingUrl($this))->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $small = $this->getJson(listingUrl($this), listingInertiaHeaders())->assertOk();
    $smallQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    for ($i = 0; $i < 30; $i++) {
        listingRecord($this);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $large = $this->getJson(listingUrl($this), listingInertiaHeaders())->assertOk();
    $largeQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $small->assertJsonCount(10, 'props.wasteRecords.data')->assertJsonPath('props.wasteRecords.total', 10);
    $large->assertJsonCount(10, 'props.wasteRecords.data')->assertJsonPath('props.wasteRecords.total', 40);
    expect($smallQueries)->toBeGreaterThan(0);
    expect($largeQueries)->toBeLessThanOrEqual($smallQueries + 1);
});

test('legacy record clients receive every visible row unless they explicitly negotiate contract two', function (?string $contract) {
    $this->withoutHeader('X-Waspro-Records-Contract');
    if ($contract !== null) {
        $this->withHeader('X-Waspro-Records-Contract', $contract);
    }
    $ids = collect(range(1, 23))->map(fn ($i) => listingRecord($this, ['record_number' => 'LEGACY-'.$i, 'quantity' => $i])->id);
    $response = $this->getJson(listingUrl($this, ['page' => 999, 'search' => 'nonexistent', 'sort' => 'quantity_low']), listingInertiaHeaders())->assertOk()
        ->assertJsonCount(23, 'props.wasteRecords')->assertJsonMissingPath('props.wasteRecords.data')
        ->assertJsonPath('props.recordSummary.total', 23);
    expect(collect($response->json('props.wasteRecords'))->pluck('id')->sort()->values()->all())->toBe($ids->sort()->values()->all());
    expect($response->getVary())->toContain('X-Inertia')->toContain('X-Waspro-Records-Contract');
})->with([null, '1', '02', 'unsupported']);

test('contract two exposes every page without duplicates and preserves filter query links', function () {
    $ids = collect(range(1, 23))->map(fn ($i) => listingRecord($this, ['record_number' => 'MATRIX-'.$i, 'source' => 'Matrix source', 'quantity' => $i])->id);
    $seen = collect();
    foreach ([1, 2, 3] as $page) {
        $response = $this->getJson(listingUrl($this, ['page' => $page, 'search' => 'Matrix', 'sort' => 'quantity_low']), listingInertiaHeaders())->assertOk()
            ->assertJsonPath('props.wasteRecords.current_page', $page)->assertJsonPath('props.wasteRecords.total', 23)
            ->assertJsonPath('props.filters.search', 'Matrix')->assertJsonPath('props.filters.sort', 'quantity_low');
        $seen = $seen->merge(collect($response->json('props.wasteRecords.data'))->pluck('id'));
        expect($response->getVary())->toContain('X-Waspro-Records-Contract');
        if ($page === 1) {
            parse_str(parse_url($response->json('props.wasteRecords.next_page_url'), PHP_URL_QUERY), $query);
            expect($query)->toMatchArray(['organization_id' => $this->organization->id, 'month' => '2026-11', 'search' => 'Matrix', 'sort' => 'quantity_low', 'page' => '2']);
        }
    }
    expect($seen->all())->toBe($ids->all());
    expect($seen->unique())->toHaveCount(23);
    $this->getJson(listingUrl($this, ['search' => 'MATRIX-23']), listingInertiaHeaders())->assertOk()->assertJsonPath('props.wasteRecords.total', 1);
    $this->getJson(listingUrl($this), listingInertiaHeaders())->assertOk()->assertJsonPath('props.wasteRecords.total', 23);
    $this->getJson(listingUrl($this, ['page' => 2, 'search' => 'Matrix', 'sort' => 'quantity_low']), listingInertiaHeaders())->assertOk()
        ->assertJsonPath('props.wasteRecords.current_page', 2)->assertJsonPath('props.wasteRecords.data.0.record_number', 'MATRIX-11');
});

test('legacy and paginated record contracts keep identical attention and owner boundaries', function () {
    $own = listingRecord($this, [], '2026-11-01');
    listingRecord($this, [], '2027-01-01');
    $this->tenants->switchToPublic();
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    $this->role->permissions()->sync(Permission::where('slug', 'waste_records.view_own')->pluck('id'));
    $this->user->unsetRelation('role');
    listingRecord($this, ['created_by' => $other->id], '2026-11-01');
    foreach ([null, '2'] as $contract) {
        $this->withoutHeader('X-Waspro-Records-Contract');
        if ($contract !== null) {
            $this->withHeader('X-Waspro-Records-Contract', $contract);
        }
        $path = $contract === '2' ? 'props.wasteRecords.data' : 'props.wasteRecords';
        $this->getJson(listingUrl($this, ['attention' => 'expired']), listingInertiaHeaders())->assertOk()
            ->assertJsonCount(1, $path)->assertJsonPath($path.'.0.id', $own->id)
            ->assertJsonPath('props.filters.attention', 'expired')->assertJsonPath('props.recordSummary.total', 2);
    }
});
