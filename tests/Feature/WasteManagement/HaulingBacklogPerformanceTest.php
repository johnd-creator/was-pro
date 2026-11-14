<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenantService = app(TenantService::class);
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();
    $this->admin = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

test('hauling backlog is paginated with bounded query count and global totals', function () {
    $seed = WasteRecord::factory()->approved()->create([
        'quantity' => 100, 'unit' => 'kg', 'created_by' => $this->admin->id,
        'expiry_date' => today()->subDay(),
    ]);
    $rows = [];
    $haulings = [];
    for ($index = 0; $index < 80; $index++) {
        $id = (string) Illuminate\Support\Str::uuid();
        $rows[] = [...$seed->getAttributes(), 'id' => $id, 'record_number' => sprintf('PERF-%04d', $index)];
        $haulings[] = ['id' => (string) Illuminate\Support\Str::uuid(), 'waste_record_id' => $id,
            'hauling_number' => sprintf('PERF-HAUL-%04d', $index), 'hauling_date' => today()->toDateString(),
            'quantity' => 20, 'unit' => 'kg', 'status' => 'approved',
            'created_by' => $this->admin->id, 'submitted_by' => $this->admin->id,
            'approved_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()];
    }
    WasteRecord::query()->insert($rows);
    WasteHauling::query()->insert($haulings);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => app(App\Http\Middleware\HandleInertiaRequests::class)->version(request())];
    $url = route('waste-management.haulings.index', ['organization_id' => $this->organization->id]);
    $start = hrtime(true);
    $legacy = $this->withHeaders([...$headers, 'X-Waspro-Backlog-Contract' => '1'])->get($url);
    $legacyElapsed = round((hrtime(true) - $start) / 1e6, 2);
    fwrite(STDERR, "\nHAULING_LEGACY_METRIC ".json_encode(['records' => 81, 'queries' => $queries, 'milliseconds' => $legacyElapsed, 'bytes' => strlen($legacy->getContent())])."\n");
    $legacy->assertOk()->assertJsonCount(81, 'props.records');
    expect($legacy->getVary())->toContain('X-Inertia', 'X-Waspro-Backlog-Contract');
    $queries = 0;
    $start = hrtime(true);
    $response = $this->withHeaders([...$headers, 'X-Waspro-Backlog-Contract' => '2'])->get($url);
    $elapsed = round((hrtime(true) - $start) / 1e6, 2);
    fwrite(STDERR, "\nHAULING_BACKLOG_METRIC ".json_encode(['records' => 81, 'queries' => $queries, 'milliseconds' => $elapsed, 'bytes' => strlen($response->getContent())])."\n");
    $response->assertOk()->assertJsonCount(10, 'props.records')
        ->assertJsonPath('props.pagination.total', 81)->assertJsonPath('props.stats.total_records', 81)
        ->assertJsonPath('props.stats.remaining_quantities.0.quantity', 6500);
    expect($response->getVary())->toContain('X-Inertia', 'X-Waspro-Backlog-Contract');
    expect($queries)->toBeLessThan(100);
});

function backlogResponse($test, array $parameters = []): Illuminate\Testing\TestResponse
{
    return $test->withHeaders([
        'X-Inertia' => 'true', 'X-Waspro-Backlog-Contract' => '2',
        'X-Inertia-Version' => app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'waste-management/haulings/Index',
        'X-Inertia-Partial-Data' => 'records,stats,pagination,filters,approvedPlans,backlogContract,backlogContracts',
    ])->get(route('waste-management.haulings.index', ['organization_id' => $test->organization->id, ...$parameters]));
}

test('backlog filters search and urgency on the server while totals remain global', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-14 12:00:00', config('hauling-plans.timezone')));
    $expiryDates = [today()->subDay(), today(), today()->addDays(7), today()->addDays(8), null];
    foreach ($expiryDates as $index => $expiry) {
        $record = WasteRecord::factory()->approved()->create([
            'record_number' => 'FILTER-'.$index, 'source' => $index === 0 ? 'Unique 100%_ depot' : 'Other depot',
            'expiry_date' => $expiry, 'quantity' => 10, 'unit' => 'kg', 'created_by' => $this->admin->id,
        ]);
        WasteRecord::query()->whereKey($record->id)->update(['expiry_date' => $expiry]);
    }
    backlogResponse($this, ['urgency' => 'expired'])->assertOk()->assertJsonCount(1, 'props.records')
        ->assertJsonPath('props.records.0.record_number', 'FILTER-0')->assertJsonPath('props.stats.total_records', 5)
        ->assertJsonPath('props.stats.expired', 1)->assertJsonPath('props.stats.expiring_soon', 2)
        ->assertJsonPath('props.stats.fresh', 1)->assertJsonPath('props.stats.attention_quantities.0.quantity', 30);
    backlogResponse($this, ['urgency' => 'expiring_soon'])->assertOk()->assertJsonCount(2, 'props.records');
    backlogResponse($this, ['urgency' => 'fresh'])->assertOk()->assertJsonCount(1, 'props.records');
    backlogResponse($this, ['search' => 'unique 100%_'])->assertOk()->assertJsonCount(1, 'props.records');
    backlogResponse($this, ['search' => 'not found'])->assertOk()->assertJsonCount(0, 'props.records')->assertJsonPath('props.stats.total_records', 5);
    backlogResponse($this, ['search' => ['invalid'], 'sort' => 'invalid', 'urgency' => 'invalid'])->assertOk()
        ->assertJsonPath('props.filters', ['search' => '', 'urgency' => 'all', 'sort' => 'priority']);
});

test('backlog excludes completed deleted and unapproved records without mixing units in sort', function () {
    $records = collect();
    foreach ([['kg', 20], ['liter', 100], ['kg', 80]] as [$unit, $quantity]) {
        $records->push(WasteRecord::factory()->approved()->create([
            'quantity' => $quantity, 'unit' => $unit, 'created_by' => $this->admin->id,
        ]));
    }
    $completed = WasteRecord::factory()->approved()->create(['quantity' => 5, 'created_by' => $this->admin->id]);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $completed->id, 'quantity' => 5, 'unit' => $completed->unit, 'created_by' => $this->admin->id]);
    WasteRecord::factory()->create(['status' => 'draft', 'created_by' => $this->admin->id]);
    WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'deleted_at' => now()]);
    $response = backlogResponse($this, ['sort' => 'remaining_high'])->assertOk()->assertJsonCount(3, 'props.records')->assertJsonPath('props.stats.total_records', 3);
    expect(collect($response->json('props.records'))->pluck('id')->all())->toBe([$records[2]->id, $records[0]->id, $records[1]->id]);
    backlogResponse($this, ['page' => 999])->assertOk()->assertJsonPath('props.pagination.current_page', 1)->assertJsonCount(3, 'props.records');
});

test('backlog own scope prevents source search and totals leaking other owners', function () {
    $this->tenantService->switchToPublic();
    $operator = User::factory()->create(['organization_id' => $this->organization->id,
        'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    WasteRecord::factory()->approved()->create(['quantity' => 10, 'unit' => 'kg', 'source' => 'Visible depot', 'created_by' => $operator->id]);
    WasteRecord::factory()->approved()->create(['quantity' => 900, 'unit' => 'kg', 'source' => 'Secret depot', 'created_by' => $this->admin->id]);
    $this->actingAs($operator);
    backlogResponse($this, ['search' => 'secret'])->assertOk()->assertJsonCount(0, 'props.records')
        ->assertJsonPath('props.stats.total_records', 1)->assertJsonPath('props.stats.remaining_quantities.0.quantity', 10);
    backlogResponse($this)->assertOk()->assertJsonCount(1, 'props.records');
});

test('backlog plan balances match canonical reservations and plans are limited to the current page', function () {
    config(['hauling-plans.enabled' => true]);
    $record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->admin->id, 'source' => 'Find me']);
    $other = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->admin->id, 'source' => 'Other depot']);
    foreach ([$record, $other] as $row) {
        App\Models\WasteHaulingPlan::query()->insert([
            'id' => (string) Illuminate\Support\Str::uuid(), 'waste_record_id' => $row->id,
            'planned_date' => today()->toDateString(), 'quantity' => 80, 'unit' => 'kg', 'status' => 'approved',
            'created_by' => $this->admin->id, 'submitted_by' => $this->admin->id, 'submitted_at' => now(), 'approved_at' => now(),
        ]);
    }
    $plan = App\Models\WasteHaulingPlan::where('waste_record_id', $record->id)->firstOrFail();
    WasteHauling::query()->insert([
        'id' => (string) Illuminate\Support\Str::uuid(), 'waste_record_id' => $record->id, 'waste_hauling_plan_id' => $plan->id,
        'hauling_number' => 'PLAN-ACTUAL', 'hauling_date' => today()->toDateString(), 'quantity' => 30.25, 'unit' => 'kg',
        'status' => 'approved', 'created_by' => $this->admin->id,
    ]);
    $expected = app(App\Services\HaulingPlans::class)->summary($record);
    backlogResponse($this, ['search' => 'Find me'])->assertOk()->assertJsonCount(1, 'props.records')->assertJsonCount(1, 'props.approvedPlans')
        ->assertJsonPath('props.records.0.remaining_quantity', 69.75)
        ->assertJsonPath('props.records.0.reserved_quantity', 80)
        ->assertJsonPath('props.records.0.plan_reserved_quantity', $expected['plan_reserved_quantity'])
        ->assertJsonPath('props.records.0.available_quantity', 20)
        ->assertJsonPath('props.approvedPlans.0.id', $plan->id)
        ->assertJsonPath('props.approvedPlans.0.abilities.can_realize', true);
});

test('legacy browser clients always receive the complete array without the new contract header', function () {
    WasteRecord::factory()->count(12)->approved()->create(['quantity' => 10, 'unit' => 'kg', 'created_by' => $this->admin->id]);
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])->get(route('waste-management.haulings.index', ['organization_id' => $this->organization->id, 'page' => 2, 'backlog_contract' => 2]))
        ->assertOk()->assertJsonCount(12, 'props.records')->assertJsonPath('props.backlogContract', 1)
        ->assertJsonPath('props.backlogContracts', [1, 2])->assertJsonPath('props.initialPage', 2)
        ->assertJsonMissingPath('props.pagination');
    backlogResponse($this, ['page' => 2])->assertOk()->assertJsonCount(2, 'props.records')
        ->assertJsonPath('props.backlogContract', 2)->assertJsonPath('props.pagination.total', 12)->assertJsonPath('props.pagination.current_page', 2);
});

test('version two shared coordinator plans keep private sources and other actuals hidden', function () {
    config(['hauling-plans.enabled' => true]);
    $this->tenantService->switchToPublic();
    $coordinator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'coordinator')->value('id'), 'is_super_admin' => false]);
    $operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'source' => 'Private source 42', 'created_by' => $coordinator->id]);
    $planId = (string) Illuminate\Support\Str::uuid();
    App\Models\WasteHaulingPlan::query()->insert(['id' => $planId, 'waste_record_id' => $record->id, 'planned_date' => today()->toDateString(), 'quantity' => 80, 'unit' => 'kg', 'status' => 'approved', 'created_by' => $coordinator->id, 'submitted_by' => $coordinator->id, 'submitted_at' => now()]);
    WasteHauling::query()->insert(['id' => (string) Illuminate\Support\Str::uuid(), 'waste_record_id' => $record->id, 'waste_hauling_plan_id' => $planId,
        'hauling_number' => 'PRIVATE-ACTUAL', 'hauling_date' => today()->toDateString(), 'quantity' => 30, 'unit' => 'kg', 'status' => 'approved', 'created_by' => $coordinator->id, 'submitted_by' => $coordinator->id, 'submitted_at' => now()]);
    $this->actingAs($operator);
    backlogResponse($this)->assertOk()->assertJsonCount(1, 'props.records')
        ->assertJsonPath('props.records.0.source', null)->assertJsonPath('props.records.0.requires_plan', true)
        ->assertJsonPath('props.records.0.remaining_quantity', 70)->assertJsonPath('props.records.0.available_quantity', 0)
        ->assertJsonCount(0, 'props.records.0.hauling_history')->assertJsonCount(0, 'props.approvedPlans.0.actuals')
        ->assertJsonPath('props.approvedPlans.0.abilities.can_realize', true);
    backlogResponse($this, ['search' => 'Private source 42'])->assertOk()->assertJsonCount(0, 'props.records')->assertJsonPath('props.stats.total_records', 1);
});

test('version two aggregate and pagination never include another tenant schema', function () {
    WasteRecord::factory()->approved()->create(['quantity' => 10, 'unit' => 'kg', 'created_by' => $this->admin->id]);
    $foreign = Organization::factory()->create();
    $this->tenantService->createSchema($foreign->schema_name);
    $this->tenantService->runMigrationsForTenant($foreign->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($foreign->schema_name);
    WasteRecord::factory()->approved()->create(['quantity' => 900, 'unit' => 'kg', 'created_by' => $this->admin->id]);
    backlogResponse($this)->assertOk()->assertJsonCount(1, 'props.records')->assertJsonPath('props.stats.total_records', 1)->assertJsonPath('props.stats.remaining_quantities.0.quantity', 10);
});
