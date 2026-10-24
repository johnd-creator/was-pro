<?php

use App\Models\ApiToken;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\TenantService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenants = app(TenantService::class);
    $this->organization = Organization::factory()->create(['schema_name' => 'tenant_dashboard_'.Str::lower(Str::random(8))]);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::where('slug', 'operator')->value('id'),
    ]);
});

function dashboardToken(User $user): string
{
    $token = Str::random(40);
    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'dashboard-regression',
        'token' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    return $token;
}

test('dashboard api returns complete empty tenant metrics', function () {
    $this->withToken(dashboardToken($this->user))->getJson('/api/v1/dashboard')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.organization.id', $this->organization->id)
        ->assertJsonPath('data.waste.total_records', 0)
        ->assertJsonPath('data.faba.pending_transaction_approvals', 0)
        ->assertJsonStructure(['data' => ['waste', 'faba'], 'meta' => ['server_time']]);
});

test('dashboard api metrics remain isolated between organizations', function () {
    $this->tenants->switchToSchema($this->organization->schema_name);
    WasteRecord::factory()->draft()->count(2)->create(['created_by' => $this->user->id]);
    FabaMovement::factory()->create([
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'approval_status' => FabaMovement::STATUS_PENDING_APPROVAL,
        'created_by' => $this->user->id,
    ]);
    $this->tenants->switchToPublic();
    $other = Organization::factory()->create(['schema_name' => 'tenant_dashboard_other_'.Str::lower(Str::random(8))]);
    $this->tenants->createSchema($other->schema_name);
    $this->tenants->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $otherUser = User::factory()->create(['organization_id' => $other->id, 'role_id' => $this->user->role_id]);
    $this->tenants->switchToSchema($other->schema_name);
    WasteRecord::factory()->draft()->count(5)->create(['created_by' => $otherUser->id]);
    $this->tenants->switchToPublic();

    $this->withToken(dashboardToken($this->user))->getJson('/api/v1/dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.waste.total_records', 2)
        ->assertJsonPath('data.faba.pending_transaction_approvals', 1);
    $this->withToken(dashboardToken($otherUser))->getJson('/api/v1/dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.organization.id', $other->id)
        ->assertJsonPath('data.waste.total_records', 5)
        ->assertJsonPath('data.faba.pending_transaction_approvals', 0);
});

test('dashboard api still requires a valid bearer token', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized()->assertJsonPath('error_code', 'UNAUTHENTICATED');
    $this->withToken('invalid-token')->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('missing tenant schema fails safely without implicit provisioning', function () {
    $missing = Organization::factory()->create(['schema_name' => 'tenant_missing_'.Str::lower(Str::random(8))]);
    $user = User::factory()->create(['organization_id' => $missing->id, 'role_id' => $this->user->role_id]);

    $this->withToken(dashboardToken($user))->getJson('/api/v1/dashboard')
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'TENANT_NOT_READY');

    expect($this->tenants->schemaExists($missing->schema_name))->toBeFalse();
    $this->withToken(dashboardToken($this->user))->getJson('/api/v1/dashboard')->assertSuccessful();
});

test('missing tenant schema also fails safely on web requests', function () {
    $missing = Organization::factory()->create(['schema_name' => 'tenant_missing_web_'.Str::lower(Str::random(8))]);
    $user = User::factory()->create(['organization_id' => $missing->id, 'role_id' => $this->user->role_id]);

    $this->actingAs($user)->get('/dashboard')->assertStatus(503);

    expect($this->tenants->schemaExists($missing->schema_name))->toBeFalse();
});

test('dashboard api without an organization does not query tenant data in public', function () {
    $user = User::factory()->create(['organization_id' => null, 'is_super_admin' => true]);
    $this->partialMock(\App\Services\FabaRecapService::class, function ($mock) {
        $mock->shouldNotReceive('getYearlyRecap');
    });

    $this->withToken(dashboardToken($user))->getJson('/api/v1/dashboard')
        ->assertStatus(503)
        ->assertJsonPath('error_code', 'TENANT_NOT_READY');
    $this->withToken(dashboardToken($user))->getJson('/api/v1/auth/me')->assertSuccessful();
});
