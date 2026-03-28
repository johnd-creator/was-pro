<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
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
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

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

    $this->vendor = Vendor::query()->create([
        'name' => 'Vendor Uji',
        'code' => 'VENDOR-VIS',
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

function createTransportationForVisibilityTest(
    TenantService $tenantService,
    Organization $organization,
    WasteRecord $wasteRecord,
    Vendor $vendor,
    User $creator,
    array $attributes = []
): WasteTransportation {
    $tenantService->switchToSchema($organization->schema_name);

    $sequence = WasteTransportation::query()->count() + 1;

    $transportation = WasteTransportation::query()->create(array_merge([
        'transportation_number' => 'TR-'.$organization->code.'-2026-03-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        'waste_record_id' => $wasteRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2026-03-12',
        'quantity' => 50,
        'unit' => 'kg',
        'vehicle_number' => 'B 1234 VIS',
        'driver_name' => 'Driver Uji',
        'driver_phone' => '08123456789',
        'status' => 'pending',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ], $attributes));

    $tenantService->switchToPublic();

    return $transportation;
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

test('operator only sees own waste records', function () {
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

test('super admin can see all transportations created by another user', function () {
    $record = createWasteRecordForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $this->wasteType,
        $this->demoCreator
    );

    $firstTransportation = createTransportationForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $record,
        $this->vendor,
        $this->demoCreator,
        ['transportation_number' => 'TR-VISIBILITY-2026-03-0001']
    );

    $secondTransportation = createTransportationForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $record,
        $this->vendor,
        $this->demoCreator,
        [
            'transportation_number' => 'TR-VISIBILITY-2026-03-0002',
            'status' => 'delivered',
            'delivered_at' => now(),
            'dispatched_at' => now()->subHour(),
        ]
    );

    $response = $this->actingAs($this->john)
        ->get(route('waste-management.transportations.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/transportations/Index')
        ->has('wasteTransportations', 2)
        ->where('wasteTransportations', fn ($transportations): bool => collect($transportations)->pluck('transportation_number')->contains($firstTransportation->transportation_number)
            && collect($transportations)->pluck('transportation_number')->contains($secondTransportation->transportation_number))
        ->where('stats.pending', 1)
        ->where('stats.delivered', 1)
    );
});

test('record export csv follows own visibility scope', function () {
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

test('transportation export csv follows own visibility scope', function () {
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

    $ownTransportation = createTransportationForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $ownRecord,
        $this->vendor,
        $this->operator,
        ['transportation_number' => 'TR-VISIBILITY-2026-03-0301']
    );

    $otherTransportation = createTransportationForVisibilityTest(
        $this->tenantService,
        $this->organization,
        $otherRecord,
        $this->vendor,
        $this->demoCreator,
        ['transportation_number' => 'TR-VISIBILITY-2026-03-0302']
    );

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.transportations.export.csv'));

    $response->assertSuccessful();
    expect($response->streamedContent())
        ->toContain($ownTransportation->transportation_number)
        ->not->toContain($otherTransportation->transportation_number);
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
