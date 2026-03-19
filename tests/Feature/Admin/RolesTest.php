<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

uses()->group('admin', 'roles')
    ->beforeEach(function () {
        app()['config']->set('session.driver', 'array');
    });

test('super admin can view roles index', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    Role::factory()->create(['name' => 'Test Role', 'slug' => 'test_role']);
    Permission::factory()->create(['name' => 'Perm 1', 'slug' => 'perm_1', 'module' => 'roles']);

    $response = $this->get(route('admin.roles.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('admin/Roles')
        ->has('roles', 1)
        ->has('permissions', 1)
    );
});

test('super admin can update role permissions', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    $role = Role::factory()->create(['name' => 'Test Role', 'slug' => 'test_role']);
    $p1 = Permission::factory()->create(['name' => 'Perm 1', 'slug' => 'perm_1', 'module' => 'roles']);
    $p2 = Permission::factory()->create(['name' => 'Perm 2', 'slug' => 'perm_2', 'module' => 'roles']);

    $response = $this->put(route('admin.roles.update', $role), [
        'permissions' => [$p1->id, $p2->id],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $p1->id,
    ]);
    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $p2->id,
    ]);
});

test('user without manage permission can not update role permissions', function () {
    $viewPermission = Permission::factory()->create([
        'name' => 'View Roles',
        'slug' => 'roles.view',
        'module' => 'roles',
    ]);

    $role = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);
    $role->permissions()->attach($viewPermission->id);

    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_super_admin' => false,
    ]);

    $targetRole = Role::factory()->create(['name' => 'Target', 'slug' => 'target']);
    $permission = Permission::factory()->create([
        'name' => 'Manage Roles',
        'slug' => 'roles.manage',
        'module' => 'roles',
    ]);

    $response = $this->actingAs($user)->put(route('admin.roles.update', $targetRole), [
        'permissions' => [$permission->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('role_permissions', [
        'role_id' => $targetRole->id,
        'permission_id' => $permission->id,
    ]);
});
