<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Switch to public schema before each test
    app(\App\Services\TenantService::class)->switchToPublic();
});

test('authorization service checks permissions for authenticated user', function () {
    $organization = Organization::factory()->create();
    $role = Role::query()->where('slug', 'admin')->firstOrFail();
    $permission = Permission::query()->where('slug', 'users.view')->firstOrFail();

    $role->permissions()->syncWithoutDetaching([$permission->id]);

    $user = User::factory()->for($organization)->for($role)->create();

    actingAs($user);

    $authService = app(AuthorizationService::class);

    expect($authService->hasPermission('users.view'))->toBeTrue();
    expect($authService->hasPermission('organizations.view'))->toBeFalse();
});

test('authorization service checks roles for authenticated user', function () {
    $organization = Organization::factory()->create();
    $role = Role::query()->where('slug', 'admin')->firstOrFail();

    $user = User::factory()->for($organization)->for($role)->create();

    actingAs($user);

    $authService = app(AuthorizationService::class);

    expect($authService->hasRole('admin'))->toBeTrue();
    expect($authService->hasRole('operator'))->toBeFalse();
});

test('authorization service identifies super admin', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    actingAs($superAdmin);

    $authService = app(AuthorizationService::class);

    expect($authService->isSuperAdmin())->toBeTrue();
    expect($authService->hasPermission('any.permission'))->toBeTrue();
});

test('authorization service checks organization access', function () {
    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();

    $user = User::factory()->for($organization1)->create();

    actingAs($user);

    $authService = app(AuthorizationService::class);

    expect($authService->canAccessOrganization($organization1->id))->toBeTrue();
    expect($authService->canAccessOrganization($organization2->id))->toBeFalse();
});

test('authorization service returns null for unauthenticated user', function () {
    $authService = app(AuthorizationService::class);

    expect($authService->hasPermission('any.permission'))->toBeFalse();
    expect($authService->hasRole('any.role'))->toBeFalse();
    expect($authService->isSuperAdmin())->toBeFalse();
    expect($authService->canAccessOrganization('some-id'))->toBeFalse();
});

test('authorization service gets user organization id', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    actingAs($user);

    $authService = app(AuthorizationService::class);

    expect($authService->getUserOrganizationId())->toBe($organization->id);
});

test('authorization service checks if user can access tenant schema', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    actingAs($user);

    $authService = app(AuthorizationService::class);

    expect($authService->canAccessTenantSchema())->toBeTrue();
});
