<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $response = $this->getJson(route('admin.roles.index'));

    $response->assertSuccessful();
    $response->assertJsonPath('roles.0.slug', 'admin');
    $response->assertJsonFragment(['slug' => 'test_role']);
    $response->assertJsonFragment(['slug' => 'perm_1']);
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
    $viewPermission = Permission::query()->where('slug', 'roles.view')->firstOrFail();

    $role = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff']);
    $role->permissions()->attach($viewPermission->id);

    $user = User::factory()->create([
        'role_id' => $role->id,
        'is_super_admin' => false,
    ]);

    $targetRole = Role::factory()->create(['name' => 'Target', 'slug' => 'target']);
    $permission = Permission::query()->where('slug', 'roles.manage')->firstOrFail();

    $response = $this->actingAs($user)->put(route('admin.roles.update', $targetRole), [
        'permissions' => [$permission->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('role_permissions', [
        'role_id' => $targetRole->id,
        'permission_id' => $permission->id,
    ]);
});
