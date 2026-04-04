<?php

use App\Models\ApiToken;
use App\Models\FabaInternalDestination;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);

    $this->organization = Organization::factory()->create([
        'code' => 'APIFABA',
        'schema_name' => 'tenant_api_faba',
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
        'faba_production.view',
        'faba_production.create',
        'faba_production.edit',
        'faba_production.delete',
        'faba_utilization.view',
        'faba_utilization.create',
        'faba_utilization.edit',
        'faba_utilization.delete',
        'faba_adjustments.view',
        'faba_adjustments.create',
        'faba_adjustments.edit',
        'faba_adjustments.delete',
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

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $operatorRole->id,
    ]);

    $this->token = 'faba-api-token';
    ApiToken::query()->create([
        'user_id' => $this->operator->id,
        'name' => 'faba-device',
        'token' => hash('sha256', $this->token),
        'expires_at' => now()->addDay(),
    ]);

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->vendor = Vendor::factory()->create(['is_active' => true]);
    $this->internalDestination = FabaInternalDestination::factory()->create(['is_active' => true]);
    $this->purpose = FabaPurpose::factory()->create(['is_active' => true]);
    $this->tenantService->switchToPublic();
});

test('faba options endpoint returns mobile lookup data', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/faba/options');

    $response->assertSuccessful()
        ->assertJsonFragment(['fly_ash'])
        ->assertJsonPath('data.vendors.0.id', $this->vendor->id);
});

test('operator can create production movement via api', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/production', [
        'transaction_date' => now()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'quantity' => 5,
        'note' => 'Daily production input',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.movement_type', FabaMovement::TYPE_PRODUCTION)
        ->assertJsonPath('data.approval_status', FabaMonthlyApproval::STATUS_DRAFT);
});

test('operator can create utilization movement via api', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/utilization', [
        'transaction_date' => now()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'vendor_id' => $this->vendor->id,
        'purpose_id' => $this->purpose->id,
        'quantity' => 2.5,
        'document_number' => 'DOC-001',
        'document_date' => now()->toDateString(),
        'note' => 'External utilization',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.vendor.id', $this->vendor->id)
        ->assertJsonPath('data.movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL);
});

test('adjustment out is rejected when stock is insufficient via api', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', [
        'transaction_date' => now()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_ADJUSTMENT_OUT,
        'quantity' => 10,
        'note' => 'Stock correction',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

test('adjustment out succeeds when there is enough stock via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => now()->subDay()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 20,
        'unit' => FabaMovement::DEFAULT_UNIT,
        'period_year' => (int) now()->year,
        'period_month' => (int) now()->month,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', [
        'transaction_date' => now()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_ADJUSTMENT_OUT,
        'quantity' => 10,
        'note' => 'Stock correction valid',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.movement_type', FabaMovement::TYPE_ADJUSTMENT_OUT);
});

test('locked period rejects production update via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $movement = FabaMovement::factory()->create([
        'transaction_date' => '2026-03-10',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 4,
        'unit' => FabaMovement::DEFAULT_UNIT,
        'period_year' => 2026,
        'period_month' => 3,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    FabaMonthlyApproval::query()->create([
        'year' => 2026,
        'month' => 3,
        'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->token)->putJson('/api/v1/faba/production/'.$movement->id, [
        'transaction_date' => '2026-03-10',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'quantity' => 6,
        'note' => 'Should be blocked',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error_code', 'CONFLICT');
});
