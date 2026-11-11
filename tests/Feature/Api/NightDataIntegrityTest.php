<?php

use App\Models\ApiToken;
use App\Models\FabaInternalDestination;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->tenantService = app(TenantService::class);
    $this->organization = Organization::factory()->create();
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();
    $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->other = User::factory()->create(['organization_id' => $this->organization->id, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->token = 'night-data-admin';
    ApiToken::query()->create(['user_id' => $this->admin->id, 'name' => 'night-data', 'token' => hash('sha256', $this->token), 'expires_at' => now()->addDay()]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function nightFabaFixture($test, string $domain, string $status = 'pending_approval'): array
{
    $type = match ($domain) {
        'production' => FabaMovement::TYPE_PRODUCTION,
        'utilization' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        default => FabaMovement::TYPE_ADJUSTMENT_IN,
    };
    $payload = ['transaction_date' => '2026-01-10', 'material_type' => 'fly_ash', 'movement_type' => $type, 'quantity' => 2, 'note' => 'Night audit correction'];
    if ($domain === 'utilization') {
        $payload['internal_destination_id'] = FabaInternalDestination::factory()->create(['is_active' => true])->id;
        FabaMovement::factory()->create(['transaction_date' => '2026-01-01', 'period_year' => 2026, 'period_month' => 1, 'material_type' => 'fly_ash', 'movement_type' => 'production', 'stock_effect' => 'in', 'quantity' => 100, 'approval_status' => 'approved']);
    }
    $movement = FabaMovement::factory()->create([...$payload, 'stock_effect' => $domain === 'utilization' ? 'out' : 'in', 'period_year' => 2026, 'period_month' => 1, 'approval_status' => $status, 'created_by' => $test->other->id, 'submitted_by' => $test->other->id]);

    return [$movement, $payload];
}

test('true super admin can update and delete eligible other owned faba through api', function (string $domain, string $status) {
    [$movement, $payload] = nightFabaFixture($this, $domain, $status);
    $url = '/api/v1/faba/'.$domain.'/'.$movement->id;
    $this->withToken($this->token)->getJson($url)->assertOk()->assertJsonPath('data.allowed_actions', fn ($actions) => in_array('update', $actions, true) && in_array('delete', $actions, true));
    $this->withToken($this->token)->putJson($url, $payload)->assertOk()->assertJsonPath('data.approval_status', 'pending_approval');
    $this->withToken($this->token)->deleteJson($url)->assertOk();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::query()->find($movement->id))->toBeNull();
})->with(['production', 'utilization', 'adjustments'])->with(['pending_approval', 'rejected']);

test('faba api still denies approved and locked modifications for super admin', function (string $domain, string $state) {
    [$movement, $payload] = nightFabaFixture($this, $domain, $state === 'approved' ? 'approved' : 'pending_approval');
    if ($state === 'locked') {
        FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 1, 'status' => 'submitted', 'submitted_by' => $this->other->id]);
    }
    $url = '/api/v1/faba/'.$domain.'/'.$movement->id;
    $this->withToken($this->token)->getJson($url)->assertOk()->assertJsonPath('data.allowed_actions', fn ($actions) => ! in_array('update', $actions, true) && ! in_array('delete', $actions, true));
    $this->withToken($this->token)->putJson($url, $payload)->assertStatus($state === 'approved' ? 403 : 409);
    $this->withToken($this->token)->deleteJson($url)->assertStatus($state === 'approved' ? 403 : 409);
})->with(['production', 'utilization', 'adjustments'])->with(['approved', 'locked']);

test('ordinary operator still cannot modify other owned faba through api', function (string $domain) {
    [$movement, $payload] = nightFabaFixture($this, $domain);
    $this->tenantService->switchToPublic();
    $role = Role::query()->where('slug', 'operator')->firstOrFail();
    $this->admin->update(['is_super_admin' => false, 'role_id' => $role->id]);
    $url = '/api/v1/faba/'.$domain.'/'.$movement->id;
    $this->withToken($this->token)->putJson($url, $payload)->assertForbidden();
    $this->withToken($this->token)->deleteJson($url)->assertForbidden();
})->with(['production', 'utilization', 'adjustments']);

test('waste expiry follows changed type consistently on web and api', function (string $channel, int $days) {
    $oldType = WasteType::factory()->create(['storage_period_days' => 30]);
    $newType = WasteType::factory()->create(['storage_period_days' => $days]);
    $record = WasteRecord::factory()->create(['date' => '2026-01-01', 'waste_type_id' => $oldType->id, 'created_by' => $this->admin->id, 'status' => 'draft']);
    $payload = ['date' => '2026-01-01', 'waste_type_id' => $newType->id, 'quantity' => 10, 'unit' => 'kg', 'source' => 'Night audit'];
    if ($channel === 'api') {
        $this->withToken($this->token)->putJson('/api/v1/waste-records/'.$record->id, $payload)->assertOk();
    } else {
        $this->actingAs($this->admin)->put(route('waste-management.records.update', [$record->id, 'organization_id' => $this->organization->id]), $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    }
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $fresh = WasteRecord::factory()->create([...$payload, 'status' => 'draft']);
    expect($record->fresh()->expiry_date?->toDateString())->toBe($fresh->expiry_date?->toDateString())->toBe($days === 0 ? null : '2026-03-02');
})->with(['web', 'api'])->with([0, 60]);

test('hauling detail mirrors separation while preserving legacy rejection', function (bool $planned, bool $owner) {
    config(['hauling-plans.enabled' => true]);
    $record = WasteRecord::factory()->approved()->create(['date' => '2026-01-01', 'quantity' => 100, 'unit' => 'kg']);
    $plan = null;
    if ($planned) {
        $plan = WasteHaulingPlan::query()->create(['waste_record_id' => $record->id, 'quantity' => 50, 'unit' => 'kg', 'planned_date' => '2026-01-10', 'submitted_at' => now(), 'status' => 'pending_approval', 'created_by' => $this->admin->id, 'submitted_by' => $this->admin->id]);
        $this->actingAs($this->other);
        $plan->approve($this->other->id);
    }
    $this->actingAs($this->admin);
    $hauling = WasteHauling::factory()->create(['waste_record_id' => $record->id, 'waste_hauling_plan_id' => $plan?->id, 'hauling_date' => '2026-01-10', 'quantity' => 10, 'unit' => 'kg', 'status' => 'pending_approval', 'created_by' => $this->admin->id, 'submitted_by' => $this->admin->id]);
    $this->actingAs($owner ? $this->admin : $this->other)->get(route('waste-management.haulings.show', [$hauling->id, 'organization_id' => $this->organization->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('abilities.can_approve', ! $owner)->where('abilities.can_reject', ! $planned || ! $owner));
})->with([false, true])->with([false, true]);
