<?php

use App\Models\ApiToken;
use App\Models\FabaMovement;
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
        'code' => 'APICONTRACT',
        'schema_name' => 'tenant_api_contract',
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

    $permissionIds = collect([
        'waste_records.view_own',
        'waste_records.create',
        'faba_production.view',
    ])->map(function (string $slug) {
        [$module] = explode('.', $slug);

        return Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'module' => $module,
                'description' => $slug,
                'is_active' => true,
            ],
        )->id;
    });

    $operatorRole->permissions()->syncWithoutDetaching($permissionIds->all());

    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $operatorRole->id,
    ]);

    $this->token = 'api-contract-token';
    ApiToken::query()->create([
        'user_id' => $this->user->id,
        'name' => 'api-contract-device',
        'token' => hash('sha256', $this->token),
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

    WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'record_number' => 'WR-CONTRACT-001',
        'source' => 'Warehouse',
        'description' => 'Contract record',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-10',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 8,
        'unit' => FabaMovement::DEFAULT_UNIT,
        'period_year' => 2026,
        'period_month' => 3,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);
    $this->tenantService->switchToPublic();
});

test('protected mobile api returns standardized unauthenticated envelope', function () {
    $response = $this->getJson('/api/v1/waste-records');

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'UNAUTHENTICATED')
        ->assertJsonPath('errors', []);
});

test('mobile api validation errors use standardized envelope', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/waste-records', []);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonPath('message', 'Data yang diberikan tidak valid.')
        ->assertJsonStructure([
            'errors' => ['date', 'waste_type_id', 'quantity', 'unit'],
        ]);
});

test('mobile api missing resources use standardized not found envelope', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/waste-records/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'NOT_FOUND')
        ->assertJsonPath('message', 'Resource tidak ditemukan.');
});

dataset('mobile_list_endpoints', [
    'waste records' => ['/api/v1/waste-records?search=Warehouse&status=draft&per_page=5'],
    'faba production' => ['/api/v1/faba/production?year=2026&month=3&material_type=fly_ash&per_page=5'],
]);

test('mobile list endpoints expose consistent pagination filter and server meta', function (string $endpoint) {
    $response = $this->withToken($this->token)->getJson($endpoint);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'meta' => [
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                'filters',
                'server_time',
            ],
        ]);
})->with('mobile_list_endpoints');

test('tenant isolation prevents cross organization resource access in mobile api', function () {
    $otherOrganization = Organization::factory()->create([
        'code' => 'APICONTRACT2',
        'schema_name' => 'tenant_api_contract_2',
    ]);

    if (! $this->tenantService->schemaExists($otherOrganization->schema_name)) {
        $this->tenantService->createSchema($otherOrganization->schema_name);
    }

    $this->tenantService->switchToSchema($otherOrganization->schema_name);
    $this->tenantService->runMigrationsForTenant($otherOrganization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();

    $otherUser = User::factory()->create([
        'organization_id' => $otherOrganization->id,
        'role_id' => $this->user->role_id,
    ]);

    $otherToken = 'api-contract-token-2';
    ApiToken::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'api-contract-device-2',
        'token' => hash('sha256', $otherToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::query()->firstOrFail();
    $this->tenantService->switchToPublic();

    $response = $this->withToken($otherToken)
        ->getJson('/api/v1/waste-records/'.$record->id);

    $response->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND');
});
