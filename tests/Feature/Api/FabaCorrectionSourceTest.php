<?php

use App\Models\ApiToken;
use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);
    $suffix = Str::lower(Str::random(8));

    $this->organization = Organization::factory()->create([
        'code' => 'APIFABA'.$suffix,
        'schema_name' => 'tenant_api_faba_'.$suffix,
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
        'faba_approvals.view',
        'faba_approvals.approve',
        'faba_approvals.reject',
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

function correctionPayload(array $extra = []): array
{
    return array_replace([
        'transaction_date' => now()->toDateString(),
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_ADJUSTMENT_IN,
        'quantity' => 2,
        'note' => 'Koreksi hasil pemeriksaan',
    ], $extra);
}

function correctionSource($test, array $extra = []): FabaMovement
{
    $test->tenantService->switchToSchema($test->organization->schema_name);
    $source = FabaMovement::factory()->create(array_replace([
        'transaction_date' => now()->toDateString(),
        'period_year' => now()->year,
        'period_month' => now()->month,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 10,
        'approval_status' => FabaMovement::STATUS_APPROVED,
        'attachment_path' => 'private/secret.pdf',
    ], $extra));
    $test->tenantService->switchToPublic();

    return $source;
}

test('correction retains captured source evidence and preserves linkage for legacy omitted field updates', function () {
    $source = correctionSource($this);
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]));
    $response->assertCreated()->assertJsonPath('data.source_movement_id', $source->id)
        ->assertJsonPath('data.source_snapshot.quantity', 10)
        ->assertJsonMissing(['attachment_path' => 'private/secret.pdf']);
    $id = $response->json('data.id');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $source->update(['quantity' => 12]);
    $this->tenantService->switchToPublic();
    $this->withToken($this->token)->putJson('/api/v1/faba/adjustments/'.$id, correctionPayload())
        ->assertSuccessful()->assertJsonPath('data.source_snapshot.quantity', 10)
        ->assertJsonPath('data.source_current.quantity', 12)->assertJsonPath('data.source_movement_id', $source->id);
    $this->withToken($this->token)->putJson('/api/v1/faba/adjustments/'.$id, correctionPayload(['material_type' => 'bottom_ash']))
        ->assertUnprocessable()->assertJsonValidationErrors('source_movement_id');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(fn () => $source->delete())->toThrow(\App\Exceptions\OperationalConflict::class);
    expect(FabaMovement::query()->findOrFail($id)->material_type)->toBe('fly_ash');
    expect(\App\Models\FabaAuditLog::query()->where('action', 'source_linked')->where('reference_id', $id)->count())->toBe(1);
});

test('correction rejects unavailable or incompatible source and whitespace reason', function (string $case) {
    $source = correctionSource($this, match ($case) {
        'material' => ['material_type' => 'bottom_ash'],
        'pending' => ['approval_status' => 'pending_approval'],
        default => [],
    });
    $payload = correctionPayload(['source_movement_id' => $case === 'missing' ? (string) Str::uuid() : $source->id]);
    if ($case === 'whitespace') {
        $payload['note'] = '   ';
    }
    if ($case === 'class') {
        $payload['reference_type'] = User::class;
    }
    $field = match ($case) {
        'whitespace' => 'note', 'class' => 'reference_type', default => 'source_movement_id'
    };
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['material', 'pending', 'missing', 'whitespace', 'class']);

test('all approved movement types are descriptive correction sources without automatic reversal', function (string $type) {
    $source = correctionSource($this, ['movement_type' => $type]);
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))
        ->assertCreated()->assertJsonPath('data.quantity', 2)->assertJsonPath('data.approval_status', 'pending_approval');
})->with(['workshop', 'reject', 'disposal_pok', 'adjustment_in', 'utilization_internal']);

test('legacy reference discriminator cannot be overwritten by source input', function () {
    $source = correctionSource($this);
    $movement = correctionSource($this, [
        'movement_type' => 'adjustment_in', 'approval_status' => 'pending_approval',
        'created_by' => $this->operator->id, 'reference_type' => 'legacy_production', 'reference_id' => (string) Str::uuid(),
    ]);
    $this->withToken($this->token)->putJson('/api/v1/faba/adjustments/'.$movement->id, correctionPayload())
        ->assertSuccessful()->assertJsonPath('data.source_reference_reserved', true);
    $this->withToken($this->token)->putJson('/api/v1/faba/adjustments/'.$movement->id, correctionPayload(['source_movement_id' => $source->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('source_movement_id');
});

test('source module permissions protect submitted IDs and source evidence after revocation', function () {
    $source = correctionSource($this);
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))->assertCreated();
    $this->tenantService->switchToPublic();
    $role = Role::query()->create(['name' => 'Correction only', 'slug' => 'correction-only', 'level' => 1, 'is_active' => true]);
    $role->permissions()->sync(Permission::query()->whereIn('slug', ['faba_adjustments.view', 'faba_adjustments.create', 'faba_adjustments.edit'])->pluck('id'));
    $this->operator->update(['role_id' => $role->id]);
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('source_movement_id');
    $this->withToken($this->token)->getJson('/api/v1/faba/adjustments/'.$response->json('data.id'))
        ->assertSuccessful()->assertJsonPath('data.source_current', null)->assertJsonPath('data.source_snapshot', null);
});

test('web adjustment forms expose permitted source choices and save source evidence', function () {
    $source = correctionSource($this);
    $this->actingAs($this->operator)->get('/waste-management/faba/adjustments/create')
        ->assertSuccessful()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('waste-management/faba/adjustments/Create')->has('sourceOptions', 1)->where('sourceOptions.0.id', $source->id));
    $this->actingAs($this->operator)->post('/waste-management/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $movement = FabaMovement::query()->where('reference_id', $source->id)->firstOrFail();
    $this->tenantService->switchToPublic();
    $this->actingAs($this->operator)->get('/waste-management/faba/adjustments/'.$movement->id)
        ->assertSuccessful()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
        ->component('waste-management/faba/adjustments/Show')->where('entry.source_snapshot.id', $source->id));
});

test('source from another tenant and direct self-reference are rejected', function () {
    $other = Organization::factory()->create(['schema_name' => 'tenant_src_'.Str::lower(Str::random(8))]);
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->switchToSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $foreign = FabaMovement::factory()->create(['material_type' => 'fly_ash']);
    $this->tenantService->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('source_movement_id');
    $source = correctionSource($this);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(fn () => app(\App\Services\FabaCorrectionSource::class)->attributes(correctionPayload(['source_movement_id' => $source->id]), $source))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('opening source evidence does not reset stock and helper cannot delete referenced source', function () {
    $this->tenantService->switchToPublic();
    $permission = Permission::query()->firstOrCreate(['slug' => 'faba_recaps.view'], ['name' => 'View recaps', 'module' => 'faba_recaps', 'is_active' => true]);
    $this->operator->role->permissions()->syncWithoutDetaching([$permission->id]);
    $source = correctionSource($this, ['movement_type' => 'opening_balance', 'reference_type' => \App\Models\FabaOpeningBalance::class, 'reference_id' => (string) Str::uuid()]);
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))->assertCreated();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(app(\App\Services\FabaMovementLedgerService::class)->calculateAvailableStock('fly_ash', now()))->toBe(10.0);
    expect(fn () => app(\App\Services\FabaMovementLedgerService::class)->deleteReferenceMovements($source->reference_type, $source->reference_id))
        ->toThrow(\App\Exceptions\OperationalConflict::class);
    expect(FabaMovement::query()->find($source->id))->not->toBeNull();
});

test('source linked correction still respects locked target period', function () {
    $source = correctionSource($this);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    \App\Models\FabaMonthlyApproval::factory()->create(['year' => now()->year, 'month' => now()->month, 'status' => 'approved']);
    $this->tenantService->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))->assertConflict();
});

test('faba inputs reject positive quantities that round to zero', function (string $endpoint) {
    $payload = correctionPayload(['quantity' => 0.001]);
    if ($endpoint === 'production') {
        $payload['movement_type'] = 'production';
    } elseif ($endpoint === 'utilization') {
        $payload['movement_type'] = 'utilization_internal';
        $payload['internal_destination_id'] = $this->internalDestination->id;
    }
    $this->withToken($this->token)->postJson('/api/v1/faba/'.$endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');
})->with(['production', 'utilization', 'adjustments']);

test('unlink and relink captures new evidence while retaining prior linkage audit', function () {
    $source = correctionSource($this);
    $response = $this->withToken($this->token)->postJson('/api/v1/faba/adjustments', correctionPayload(['source_movement_id' => $source->id]))->assertCreated();
    $url = '/api/v1/faba/adjustments/'.$response->json('data.id');
    $this->withToken($this->token)->putJson($url, correctionPayload(['source_movement_id' => null]))->assertSuccessful()->assertJsonPath('data.source_movement_id', null);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $source->update(['quantity' => 13]);
    $this->tenantService->switchToPublic();
    $this->travel(1)->seconds();
    $this->withToken($this->token)->putJson($url, correctionPayload(['source_movement_id' => $source->id]))
        ->assertSuccessful()->assertJsonPath('data.source_snapshot.quantity', 13);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(\App\Models\FabaAuditLog::query()->where('reference_id', $response->json('data.id'))->where('action', 'source_linked')->count())->toBe(2);
});
