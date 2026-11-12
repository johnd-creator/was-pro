<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    bootstrapFeatureTestCase();
    $this->tenant = app(TenantService::class);
    $this->organization = $this->tenant->provisionOrganization(['name' => 'Guide Synthetic', 'code' => 'GUIDE', 'is_active' => true]);
    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::where('slug', 'operator')->firstOrFail()->id,
        'is_super_admin' => false,
    ]);
    $this->superAdmin = User::factory()->create(['organization_id' => null, 'is_super_admin' => true]);
    $this->tenant->switchToPublic();
});

test('guide requires authentication', function () {
    $this->get(route('guide'))->assertRedirect(route('login'));
});

test('authenticated users can read guide without organization or assigned role', function () {
    $user = User::factory()->create(['organization_id' => null, 'role_id' => null, 'is_super_admin' => false]);
    $this->actingAs($user)->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Guide')->where('guideTenantReady', false)
        ->where('guideRole.slug', null)->where('organizationContext.organization', null)
        ->where('guideModules.waste', false)->where('guideModules.faba', false)
        ->where('guideModules.audit', false)->where('hauling_plans_available', false));
});

test('guide exposes actual role and permissions but no tenant operational data', function () {
    $this->actingAs($this->operator)->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Guide')->where('guideRole.slug', 'operator')->where('guideTenantReady', true)
        ->where('organizationContext.organization.id', $this->organization->id)
        ->where('guideModules.waste', true)->where('guideModules.faba', true)->where('guideModules.audit', true)->where('guideModules.fabaMasters', true)
        ->where('auth.user.permissions', fn ($permissions) => in_array('waste_records.view_all', $permissions->all()) && ! in_array('waste_hauling.approve', $permissions->all()))
        ->missing('records')->missing('entries')->missing('notifications')->missing('plans'));
});

test('guide evaluates feature availability in selected superadmin tenant and restores search path', function () {
    config(['hauling-plans.enabled' => true, 'operational-inbox.enabled' => true]);
    $before = DB::selectOne('SHOW search_path')->search_path;
    $this->actingAs($this->superAdmin)->get(route('guide', ['organization_id' => $this->organization->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideRole.slug', 'super_admin')->where('guideTenantReady', true)
        ->where('organizationContext.organization.id', $this->organization->id)
        ->where('hauling_plans_available', true)->where('operational_inbox_available', true));
    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($before);
    $this->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizationContext.organization.id', $this->organization->id)->where('hauling_plans_available', true));
});

test('guide keeps feature flags disabled even on provisioned tenant', function () {
    config(['hauling-plans.enabled' => false, 'operational-inbox.enabled' => false]);
    $this->actingAs($this->operator)->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideTenantReady', true)->where('hauling_plans_available', false)->where('operational_inbox_available', false));
});

test('guide safely handles legacy empty schema without exposing optional links', function () {
    config(['hauling-plans.enabled' => true, 'operational-inbox.enabled' => true]);
    $legacy = Organization::factory()->create(['schema_name' => 'tenant_guide_empty']);
    $this->tenant->createSchema($legacy->schema_name);
    $this->actingAs($this->superAdmin)->get(route('guide', ['organization_id' => $legacy->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('notificationSummary.total_count', 0)->has('operationalAlerts', 0)
        ->where('guideTenantReady', true)->where('guideModules.waste', false)->where('guideModules.faba', false)
        ->where('guideModules.audit', false)->where('hauling_plans_available', false)->where('operational_inbox_available', false));
    $this->get(route('profile.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('notificationSummary.total_count', 0)->has('operationalAlerts', 0));
});

test('guide is available when assigned tenant schema is missing without provisioning it', function () {
    $missing = Organization::factory()->create(['schema_name' => 'tenant_guide_missing']);
    $this->operator->update(['organization_id' => $missing->id]);
    $this->actingAs($this->operator->fresh())->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideTenantReady', false)->where('guideModules.waste', false)
        ->where('organizationContext.organization.id', $missing->id));
    expect($this->tenant->schemaExists($missing->schema_name))->toBeFalse();
});

test('guide rejects organization override by ordinary user and never changes role from query', function () {
    $foreign = Organization::factory()->create();
    $this->actingAs($this->operator)->get(route('guide', ['organization_id' => $foreign->id]))->assertForbidden();
    $this->get(route('guide', ['role' => 'super_admin']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideRole.slug', 'operator')->where('auth.user.is_super_admin', false));
    expect($this->operator->fresh()->role->slug)->toBe('operator');
});

test('guide can clear superadmin context and keeps global identity separate from role name', function () {
    config(['hauling-plans.enabled' => true, 'operational-inbox.enabled' => true]);
    $this->actingAs($this->superAdmin)->get(route('guide', ['organization_id' => $this->organization->id]))->assertOk();
    $this->get(route('guide', ['organization_id' => '']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideTenantReady', false)->where('organizationContext.organization', null)
        ->where('hauling_plans_available', false)->where('operational_inbox_available', false));
});

test('guide without organization cannot inherit capabilities from a previous tenant connection', function () {
    config(['hauling-plans.enabled' => true, 'operational-inbox.enabled' => true]);
    $this->tenant->switchToSchema($this->organization->schema_name);
    $before = DB::selectOne('SHOW search_path')->search_path;
    $this->actingAs($this->superAdmin)->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('guideTenantReady', false)->where('guideModules.fabaMasters', false)
        ->where('hauling_plans_available', false)->where('operational_inbox_available', false));
    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($before);
    $this->tenant->switchToPublic();
});
