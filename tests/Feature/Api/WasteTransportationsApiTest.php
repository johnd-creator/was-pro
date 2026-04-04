<?php

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use App\Services\TenantService;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);

    $this->organization = Organization::factory()->create([
        'code' => 'APITRNS',
        'schema_name' => 'tenant_api_transportations',
    ]);

    if (! $this->tenantService->schemaExists($this->organization->schema_name)) {
        $this->tenantService->createSchema($this->organization->schema_name);
    }

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();

    $operatorRole = Role::query()->firstOrCreate(
        ['slug' => 'operator'],
        [
            'name' => 'Operator',
            'description' => 'Operator role',
            'level' => 1,
            'is_active' => true,
        ],
    );
    $supervisorRole = Role::query()->firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'description' => 'Supervisor role',
            'level' => 2,
            'is_active' => true,
        ],
    );

    $permissions = collect([
        'transportation.view_own' => ['name' => 'View Own Transportation', 'module' => 'transportation'],
        'transportation.view_all' => ['name' => 'View All Transportation', 'module' => 'transportation'],
        'transportation.create' => ['name' => 'Create Transportation', 'module' => 'transportation'],
        'transportation.edit' => ['name' => 'Edit Transportation', 'module' => 'transportation'],
        'transportation.delete' => ['name' => 'Delete Transportation', 'module' => 'transportation'],
        'transportation.dispatch' => ['name' => 'Dispatch Transportation', 'module' => 'transportation'],
        'transportation.deliver' => ['name' => 'Deliver Transportation', 'module' => 'transportation'],
        'transportation.cancel' => ['name' => 'Cancel Transportation', 'module' => 'transportation'],
    ])->map(function (array $definition, string $slug) {
        return Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $definition['name'],
                'module' => $definition['module'],
                'description' => $definition['name'],
                'is_active' => true,
            ],
        )->id;
    });

    $operatorRole->permissions()->syncWithoutDetaching([
        $permissions['transportation.view_own'],
        $permissions['transportation.create'],
        $permissions['transportation.edit'],
        $permissions['transportation.delete'],
        $permissions['transportation.dispatch'],
        $permissions['transportation.deliver'],
        $permissions['transportation.cancel'],
    ]);
    $supervisorRole->permissions()->syncWithoutDetaching([
        $permissions['transportation.view_all'],
        $permissions['transportation.dispatch'],
        $permissions['transportation.deliver'],
        $permissions['transportation.cancel'],
    ]);

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $operatorRole->id,
    ]);
    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $supervisorRole->id,
    ]);

    $this->operatorToken = 'operator-transport-token';
    ApiToken::query()->create([
        'user_id' => $this->operator->id,
        'name' => 'operator-transport-device',
        'token' => hash('sha256', $this->operatorToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->supervisorToken = 'supervisor-transport-token';
    ApiToken::query()->create([
        'user_id' => $this->supervisor->id,
        'name' => 'supervisor-transport-device',
        'token' => hash('sha256', $this->supervisorToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $category = WasteCategory::factory()->create();
    $characteristic = WasteCharacteristic::factory()->create();
    $this->wasteType = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
        'is_active' => true,
    ]);
    $this->vendor = Vendor::factory()->create(['is_active' => true]);
    $this->record = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 100,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();
});

test('operator can create and view own transportation via api', function () {
    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/transportations', [
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'transportation_date' => now()->addDay()->toDateString(),
        'quantity' => 40,
        'vehicle_number' => 'B 1234 CD',
        'driver_name' => 'Driver Test',
        'driver_phone' => '08123456789',
        'notes' => 'First pickup',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.waste_record.id', $this->record->id)
        ->assertJsonFragment(['dispatch']);

    $detail = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/transportations/'.$response->json('data.id'));

    $detail->assertSuccessful()
        ->assertJsonPath('data.vendor.id', $this->vendor->id);
});

test('transportation options only return approved records with remaining quantity', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $fullyTransported = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 50,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    WasteTransportation::factory()->delivered()->create([
        'waste_record_id' => $fullyTransported->id,
        'vendor_id' => $this->vendor->id,
        'quantity' => 50,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/transportations/options');

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.waste_records')
        ->assertJsonPath('data.waste_records.0.id', $this->record->id);
});

test('operator cannot create transportation beyond remaining quantity via api', function () {
    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/transportations', [
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'transportation_date' => now()->addDay()->toDateString(),
        'quantity' => 120,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

test('operator can dispatch deliver and cancel transportations via api according to status', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $dispatchRecord = WasteTransportation::factory()->pending()->create([
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'quantity' => 20,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $cancelRecord = WasteTransportation::factory()->pending()->create([
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'quantity' => 10,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $dispatchResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/transportations/'.$dispatchRecord->id.'/dispatch');
    $dispatchResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'in_transit');

    $deliverResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/transportations/'.$dispatchRecord->id.'/deliver', [
            'delivery_notes' => 'Delivered to vendor warehouse',
        ]);
    $deliverResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'delivered');

    $cancelResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/transportations/'.$cancelRecord->id.'/cancel');
    $cancelResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'cancelled');
});

test('supervisor can list all transportations via api while operator sees own only', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    WasteTransportation::factory()->pending()->create([
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'quantity' => 15,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    WasteTransportation::factory()->pending()->create([
        'waste_record_id' => $this->record->id,
        'vendor_id' => $this->vendor->id,
        'quantity' => 12,
        'unit' => 'kg',
        'created_by' => $this->supervisor->id,
        'updated_by' => $this->supervisor->id,
    ]);
    $this->tenantService->switchToPublic();

    $operatorResponse = $this->withToken($this->operatorToken)->getJson('/api/v1/transportations');
    $supervisorResponse = $this->withToken($this->supervisorToken)->getJson('/api/v1/transportations');

    $operatorResponse->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1);
    $supervisorResponse->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 2);
});
