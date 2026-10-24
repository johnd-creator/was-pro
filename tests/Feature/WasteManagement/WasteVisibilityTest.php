<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

uses()->group('waste-management', 'visibility');

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'code' => 'VISIBILITY',
        'schema_name' => 'tenant_visibility',
    ]);

    $this->tenantService = app(TenantService::class);

    if (! $this->tenantService->schemaExists($this->organization->schema_name)) {
        $this->tenantService->createSchema($this->organization->schema_name);
    }

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');

    $category = WasteCategory::query()->create([
        'name' => 'Limbah B3',
        'code' => 'B3VIS',
        'description' => 'Kategori uji visibilitas',
        'is_active' => true,
    ]);

    $characteristic = WasteCharacteristic::query()->create([
        'name' => 'Toksik',
        'code' => 'TOXVIS',
        'description' => 'Karakteristik uji visibilitas',
        'is_hazardous' => true,
        'is_active' => true,
    ]);

    $this->wasteType = WasteType::query()->create([
        'name' => 'Sludge Uji',
        'code' => 'WT-VIS',
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
        'description' => 'Waste type uji visibilitas',
        'storage_period_days' => 30,
        'transport_cost' => 150000,
        'is_active' => true,
    ]);

    $this->tenantService->switchToPublic();

    $this->john = User::factory()->create([
        'email' => 'john@d.co',
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);

    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'supervisor')->value('id'),
        'email_verified_at' => now(),
    ]);

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'email_verified_at' => now(),
    ]);

    $this->demoCreator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'email_verified_at' => now(),
    ]);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function createWasteRecordForVisibilityTest(
    TenantService $tenantService,
    Organization $organization,
    WasteType $wasteType,
    User $creator,
    array $attributes = []
): WasteRecord {
    $tenantService->switchToSchema($organization->schema_name);

    $sequence = WasteRecord::query()->count() + 1;

    $record = WasteRecord::query()->create(array_merge([
        'record_number' => 'WR-'.$organization->code.'-2026-03-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'date' => '2026-03-10',
        'waste_type_id' => $wasteType->id,
        'quantity' => 100,
        'unit' => 'kg',
        'source' => 'TPS Limbah',
        'description' => 'Data uji visibilitas',
        'status' => 'approved',
        'submitted_by' => $creator->id,
        'submitted_at' => now()->subDay(),
        'approved_by' => $creator->id,
        'approved_at' => now(),
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ], $attributes));

    $tenantService->switchToPublic();

    return $record;
}

function createHaulingForVisibilityTest(
    TenantService $tenantService,
    Organization $organization,
    WasteRecord $wasteRecord,
    User $creator,
    array $attributes = []
): WasteHauling {
    $tenantService->switchToSchema($organization->schema_name);

    $sequence = WasteHauling::query()->count() + 1;

    $hauling = WasteHauling::query()->create(array_merge([
        'hauling_number' => 'HL-'.$organization->code.'-2026-03-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'waste_record_id' => $wasteRecord->id,
        'hauling_date' => '2026-03-12',
        'quantity' => 50,
        'unit' => 'kg',
        'status' => 'pending_approval',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ], $attributes));

    $tenantService->switchToPublic();

    return $hauling;
}

test('super admin can see all waste records created by another user', function () {
    $firstRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0001']
    );

    $secondRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0002']
    );

    $response = $this->actingAs($this->john)
        ->get(route('waste-management.records.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Index')
        ->has('wasteRecords', 2)
        ->where('wasteRecords', fn ($records): bool => collect($records)->pluck('record_number')->contains($firstRecord->record_number)
            && collect($records)->pluck('record_number')->contains($secondRecord->record_number))
    );
});

test('operator without view all only sees own waste records', function () {
    $this->operator->role->permissions()->detach(Permission::where('slug', 'waste_records.view_all')->value('id'));
    createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->operator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0101']
    );

    createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0102']
    );

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Index')
        ->has('wasteRecords', 1)
        ->where('wasteRecords.0.record_number', 'WR-VISIBILITY-2026-03-0101')
    );
});

test('super admin can see hauling history created by another user', function () {
    $record = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator
    );

    $firstHauling = createHaulingForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $record,
        $this->demoCreator,
        ['hauling_number' => 'HL-VISIBILITY-2026-03-0001']
    );

    $secondHauling = createHaulingForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $record,
        $this->demoCreator,
        [
            'hauling_number' => 'HL-VISIBILITY-2026-03-0002',
            'status' => 'approved',
            'approved_at' => now(),
        ]
    );

    $response = $this->actingAs($this->john)
        ->get(route('waste-management.haulings.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/haulings/Index')
        ->has('records', 1)
        ->where('records.0.hauling_history', fn ($haulings): bool => collect($haulings)->pluck('hauling_number')->contains($firstHauling->hauling_number)
            && collect($haulings)->pluck('hauling_number')->contains($secondHauling->hauling_number))
        ->where('stats.pending_requests', 1)
    );
});

test('record export csv follows own visibility scope', function () {
    $this->operator->role->permissions()->detach(Permission::where('slug', 'waste_records.view_all')->value('id'));
    $ownRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->operator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0201']
    );

    $otherRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0202']
    );

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.export.csv'));

    $response->assertSuccessful();
    expect($response->streamedContent())
        ->toContain($ownRecord->record_number)
        ->not->toContain($otherRecord->record_number);
});

test('hauling details follow own visibility scope', function () {
    $ownRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->operator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0301']
    );

    $otherRecord = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator,
        ['record_number' => 'WR-VISIBILITY-2026-03-0302']
    );

    $ownHauling = createHaulingForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $ownRecord,
        $this->operator,
        ['hauling_number' => 'HL-VISIBILITY-2026-03-0301']
    );

    $otherHauling = createHaulingForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $otherRecord,
        $this->demoCreator,
        ['hauling_number' => 'HL-VISIBILITY-2026-03-0302']
    );

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.haulings.show', $ownHauling));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/haulings/Show')
        ->where('hauling.hauling_number', $ownHauling->hauling_number)
    );

    $this->actingAs($this->operator)
        ->get(route('waste-management.haulings.show', $otherHauling))
        ->assertForbidden();
});

test('supervisor can open pending approval page through has permission flow', function () {
    createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->operator,
        [
            'record_number' => 'WR-VISIBILITY-2026-03-0401',
            'status' => 'pending_review',
            'approved_by' => null,
            'approved_at' => null,
        ]
    );

    $response = $this->actingAs($this->supervisor)
        ->get(route('waste-management.records.pending-approval'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/PendingApproval')
        ->has('wasteRecords', 1)
    );
});
