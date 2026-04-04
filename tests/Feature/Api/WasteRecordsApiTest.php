<?php

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);

    $this->organization = Organization::factory()->create([
        'code' => 'APIWASTE',
        'schema_name' => 'tenant_api_waste',
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
        'waste_records.view_own' => ['name' => 'View Own Waste Records', 'module' => 'waste_records'],
        'waste_records.view_all' => ['name' => 'View All Waste Records', 'module' => 'waste_records'],
        'waste_records.create' => ['name' => 'Create Waste Records', 'module' => 'waste_records'],
        'waste_records.edit_own' => ['name' => 'Edit Own Waste Records', 'module' => 'waste_records'],
        'waste_records.edit_all' => ['name' => 'Edit All Waste Records', 'module' => 'waste_records'],
        'waste_records.delete' => ['name' => 'Delete Waste Records', 'module' => 'waste_records'],
        'waste_records.submit' => ['name' => 'Submit Waste Records', 'module' => 'waste_records'],
        'waste_records.approve' => ['name' => 'Approve Waste Records', 'module' => 'waste_records'],
        'waste_records.reject' => ['name' => 'Reject Waste Records', 'module' => 'waste_records'],
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
        $permissions['waste_records.view_own'],
        $permissions['waste_records.create'],
        $permissions['waste_records.edit_own'],
        $permissions['waste_records.delete'],
        $permissions['waste_records.submit'],
    ]);
    $supervisorRole->permissions()->syncWithoutDetaching([
        $permissions['waste_records.view_all'],
        $permissions['waste_records.approve'],
        $permissions['waste_records.reject'],
    ]);

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $operatorRole->id,
    ]);
    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $supervisorRole->id,
    ]);

    $this->operatorToken = 'operator-api-token';
    ApiToken::query()->create([
        'user_id' => $this->operator->id,
        'name' => 'operator-device',
        'token' => hash('sha256', $this->operatorToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->supervisorToken = 'supervisor-api-token';
    ApiToken::query()->create([
        'user_id' => $this->supervisor->id,
        'name' => 'supervisor-device',
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
    $this->tenantService->switchToPublic();
});

test('operator can create and view own waste records via api', function () {
    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/waste-records', [
        'date' => now()->toDateString(),
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 25.5,
        'unit' => 'kg',
        'source' => 'Warehouse',
        'description' => 'Packaging waste',
        'notes' => 'Need pickup tomorrow',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonFragment(['update'])
        ->assertJsonFragment(['submit']);

    $recordId = $response->json('data.id');

    $detail = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/waste-records/'.$recordId);

    $detail->assertSuccessful()
        ->assertJsonPath('data.created_by.id', $this->operator->id)
        ->assertJsonPath('data.waste_type.id', $this->wasteType->id);
});

test('operator list only returns own records via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $ownRecord = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->supervisor->id,
        'updated_by' => $this->supervisor->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/waste-records');

    $response->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $ownRecord->id);
});

test('operator can submit record and supervisor can approve it via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $submitResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/submit');

    $submitResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'pending_review');

    $approveResponse = $this->withToken($this->supervisorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/approve', [
            'approval_notes' => 'Approved from mobile api',
        ]);

    $approveResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.id', $this->supervisor->id);
});

test('supervisor can reject pending record and operator can return it to draft via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->pendingReview()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $rejectResponse = $this->withToken($this->supervisorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/reject', [
            'rejection_reason' => 'Data quantity needs correction first.',
        ]);

    $rejectResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected');

    $returnResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/return-to-draft');

    $returnResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonFragment(['submit']);
});

test('operator cannot approve waste record via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->pendingReview()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/approve', [
            'approval_notes' => 'Should fail',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('error_code', 'FORBIDDEN');
});
