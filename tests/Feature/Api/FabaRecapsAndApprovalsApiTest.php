<?php

use App\Models\ApiToken;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);

    $this->organization = Organization::factory()->create([
        'code' => 'APIFABA4',
        'schema_name' => 'tenant_api_faba4',
    ]);

    if (! $this->tenantService->schemaExists($this->organization->schema_name)) {
        $this->tenantService->createSchema($this->organization->schema_name);
    }

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();

    $supervisorRole = Role::query()->firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'description' => 'Supervisor role',
            'level' => 2,
            'is_active' => true,
        ],
    );

    $permissionIds = collect([
        'faba_recaps.view',
        'faba_approvals.view',
        'faba_approvals.approve',
        'faba_opening_balance.manage',
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

    $supervisorRole->permissions()->syncWithoutDetaching($permissionIds->all());

    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $supervisorRole->id,
    ]);

    $this->token = 'faba-recap-token';
    ApiToken::query()->create([
        'user_id' => $this->supervisor->id,
        'name' => 'faba-recap-device',
        'token' => hash('sha256', $this->token),
        'expires_at' => now()->addDay(),
    ]);
});

function seedFabaPeriod(TenantService $tenantService, string $schemaName, string $userId, int $year = 2026, int $month = 3): void
{
    $tenantService->switchToSchema($schemaName);

    FabaOpeningBalance::factory()->create([
        'year' => $year,
        'month' => $month,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'quantity' => 10,
        'set_by' => $userId,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => sprintf('%04d-%02d-10', $year, $month),
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 15,
        'unit' => FabaMovement::DEFAULT_UNIT,
        'period_year' => $year,
        'period_month' => $month,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    $tenantService->switchToPublic();
}

test('monthly recap endpoint returns mobile-ready payload with approval actions', function () {
    seedFabaPeriod($this->tenantService, $this->organization->schema_name, $this->supervisor->id);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/faba/recaps/monthly?year=2026&month=3');

    $response->assertSuccessful()
        ->assertJsonPath('data.detail.recap.period_label', 'Maret 2026')
        ->assertJsonPath('data.detail.recap.total_production', 15)
        ->assertJsonPath('data.approval.status', FabaMonthlyApproval::STATUS_DRAFT)
        ->assertJsonPath('data.approval.allowed_actions.0', 'review')
        ->assertJsonFragment(['submit']);
});

test('opening balance endpoint stores balance for mobile', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/faba/recaps/opening-balance', [
            'year' => 2026,
            'month' => 4,
            'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
            'quantity' => 12.5,
            'note' => 'Saldo awal mobile',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.period_label', 'April 2026')
        ->assertJsonPath('data.quantity', 12.5);

    $this->tenantService->switchToSchema($this->organization->schema_name);

    expect(FabaOpeningBalance::query()
        ->where('year', 2026)
        ->where('month', 4)
        ->where('material_type', FabaMovement::MATERIAL_BOTTOM_ASH)
        ->exists())->toBeTrue();

    $this->tenantService->switchToPublic();
});

test('supervisor can submit and approve monthly faba period via api', function () {
    seedFabaPeriod($this->tenantService, $this->organization->schema_name, $this->supervisor->id);

    $submitResponse = $this->withToken($this->token)
        ->postJson('/api/v1/faba/approvals/submit', [
            'year' => 2026,
            'month' => 3,
        ]);

    $submitResponse->assertSuccessful()
        ->assertJsonPath('data.status', FabaMonthlyApproval::STATUS_SUBMITTED)
        ->assertJsonFragment(['approve'])
        ->assertJsonFragment(['reject']);

    $approveResponse = $this->withToken($this->token)
        ->postJson('/api/v1/faba/approvals/2026/3/approve', [
            'approval_note' => 'Siap closing',
        ]);

    $approveResponse->assertSuccessful()
        ->assertJsonPath('data.status', FabaMonthlyApproval::STATUS_APPROVED)
        ->assertJsonFragment(['reopen']);

    $this->tenantService->switchToSchema($this->organization->schema_name);

    expect(FabaMonthlyClosingSnapshot::query()->forPeriod(2026, 3)->exists())->toBeTrue();

    $this->tenantService->switchToPublic();
});

test('supervisor can reject submitted monthly faba period via api', function () {
    seedFabaPeriod($this->tenantService, $this->organization->schema_name, $this->supervisor->id);

    $this->withToken($this->token)->postJson('/api/v1/faba/approvals/submit', [
        'year' => 2026,
        'month' => 3,
    ])->assertSuccessful();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/faba/approvals/2026/3/reject', [
            'rejection_note' => 'Dokumen perlu diperbaiki',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', FabaMonthlyApproval::STATUS_REJECTED)
        ->assertJsonPath('data.rejection_note', 'Dokumen perlu diperbaiki');
});

test('approved monthly period can be reopened and review endpoint returns snapshot state', function () {
    seedFabaPeriod($this->tenantService, $this->organization->schema_name, $this->supervisor->id);

    $this->withToken($this->token)->postJson('/api/v1/faba/approvals/submit', [
        'year' => 2026,
        'month' => 3,
    ])->assertSuccessful();

    $this->withToken($this->token)->postJson('/api/v1/faba/approvals/2026/3/approve', [
        'approval_note' => 'Approved',
    ])->assertSuccessful();

    $reviewResponse = $this->withToken($this->token)
        ->getJson('/api/v1/faba/approvals/2026/3');

    $reviewResponse->assertSuccessful()
        ->assertJsonPath('data.approval.status', FabaMonthlyApproval::STATUS_APPROVED)
        ->assertJsonPath('data.snapshot.status', FabaMonthlyApproval::STATUS_APPROVED);

    $reopenResponse = $this->withToken($this->token)
        ->postJson('/api/v1/faba/approvals/2026/3/reopen', [
            'reopen_note' => 'Buka lagi untuk revisi',
        ]);

    $reopenResponse->assertSuccessful()
        ->assertJsonPath('data.status', FabaMonthlyApproval::STATUS_REJECTED)
        ->assertJsonPath('data.rejection_note', 'Buka lagi untuk revisi');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    expect(FabaMonthlyClosingSnapshot::query()->forPeriod(2026, 3)->exists())->toBeFalse();

    $this->tenantService->switchToPublic();
});
