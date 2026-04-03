<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;

beforeEach(function () {
    // Switch to public schema before each test
    app(\App\Services\TenantService::class)->switchToPublic();
});

test('role can be created', function () {
    $role = Role::factory()->create();

    expect($role->id)->not->toBeNull();
    assertModelExists($role);
    assertDatabaseHas('roles', [
        'id' => $role->id,
        'name' => $role->name,
        'slug' => $role->slug,
    ]);
});

test('role has unique slug', function () {
    $role = Role::factory()->create();

    expect(fn () => Role::factory()->create(['slug' => $role->slug]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('role can have permissions', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $role->permissions()->attach($permission);

    expect($role->permissions)->toHaveCount(1);
    expect($role->hasPermission($permission->slug))->toBeTrue();
});

test('role scope active returns only active roles', function () {
    $activeRole = Role::factory()->create(['slug' => 'custom_active_role', 'is_active' => true]);
    $inactiveRole = Role::factory()->create(['slug' => 'custom_inactive_role', 'is_active' => false]);

    $activeRoleIds = Role::active()
        ->whereIn('id', [$activeRole->id, $inactiveRole->id])
        ->pluck('id');

    expect($activeRoleIds->all())->toBe([$activeRole->id]);
});

test('permission can be created', function () {
    $permission = Permission::factory()->create();

    expect($permission->id)->not->toBeNull();
    assertModelExists($permission);
    assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => $permission->name,
        'slug' => $permission->slug,
    ]);
});

test('permission has unique slug', function () {
    $permission = Permission::factory()->create();

    expect(fn () => Permission::factory()->create(['slug' => $permission->slug]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('permission can belong to roles', function () {
    $permission = Permission::factory()->create();
    $role = Role::factory()->create();

    $permission->roles()->attach($role);

    expect($permission->roles)->toHaveCount(1);
});

test('permission scope module filters by module', function () {
    $userPermissionA = Permission::factory()->create(['slug' => 'custom_users_permission_a', 'module' => 'users']);
    $organizationPermission = Permission::factory()->create(['slug' => 'custom_organizations_permission', 'module' => 'organizations']);
    $userPermissionB = Permission::factory()->create(['slug' => 'custom_users_permission_b', 'module' => 'users']);

    $userPermissionIds = Permission::module('users')
        ->whereIn('id', [$userPermissionA->id, $organizationPermission->id, $userPermissionB->id])
        ->pluck('id')
        ->sort()
        ->values();

    expect($userPermissionIds->all())->toBe([
        $userPermissionA->id,
        $userPermissionB->id,
    ]);
});

test('user can belong to organization and role', function () {
    $organization = Organization::factory()->create();
    $role = Role::factory()->create();

    $user = User::factory()->for($organization)->for($role)->create();

    expect($user->organization->id)->toBe($organization->id);
    expect($user->role->id)->toBe($role->id);
});

test('super admin has all permissions', function () {
    $user = User::factory()->create(['is_super_admin' => true]);

    expect($user->isSuperAdmin())->toBeTrue();
    expect($user->hasPermission('any.permission'))->toBeTrue();
    expect($user->hasRole('any.role'))->toBeTrue();
});

test('user without role has no permissions', function () {
    $user = User::factory()->create(['role_id' => null]);

    expect($user->hasPermission('any.permission'))->toBeFalse();
    expect($user->hasRole('any.role'))->toBeFalse();
});

test('user with role can have permissions', function () {
    $organization = Organization::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $role->permissions()->attach($permission);

    $user = User::factory()->for($organization)->for($role)->create();

    expect($user->hasPermission($permission->slug))->toBeTrue();
    expect($user->hasRole($role->slug))->toBeTrue();
});

test('user can only access their own organization unless super admin', function () {
    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();

    $user = User::factory()->for($organization1)->create();

    expect($user->canAccessOrganization($organization1->id))->toBeTrue();
    expect($user->canAccessOrganization($organization2->id))->toBeFalse();
});

test('super admin can access any organization', function () {
    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    expect($superAdmin->canAccessOrganization($organization1->id))->toBeTrue();
    expect($superAdmin->canAccessOrganization($organization2->id))->toBeTrue();
});
