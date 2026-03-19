<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

uses()->group('admin', 'users')
    ->beforeEach(function () {
        app()['config']->set('session.driver', 'array');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    });

test('super admin can view users index', function () {
    $user = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($user);

    $response = $this->getJson(route('admin.users.index'));
    $response->assertOk();
});

test('super admin can create a user', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $organization = Organization::factory()->create();
    $role = Role::factory()->create();

    $this->actingAs($superAdmin);

    $response = $this->post(route('admin.users.store'), [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_id' => $organization->id,
        'role_id' => $role->id,
        'is_super_admin' => false,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['email' => 'testuser@example.com']);
});

test('super admin can update a user', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $organization = Organization::factory()->create();
    $role = Role::factory()->create();
    $user = User::factory()->for($organization)->for($role)->create();

    $this->actingAs($superAdmin);

    $response = $this->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'organization_id' => $organization->id,
        'role_id' => $role->id,
        'is_super_admin' => false,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
});

test('super admin can delete a user', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $organization = Organization::factory()->create();
    $role = Role::factory()->create();
    $user = User::factory()->for($organization)->for($role)->create();

    $this->actingAs($superAdmin);

    $response = $this->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));
    $this->assertModelMissing($user);
});

test('super admin cannot delete themselves', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin);

    $response = $this->delete(route('admin.users.destroy', $superAdmin));

    $response->assertRedirect();
    $this->assertModelExists($superAdmin);
});

test('unauthenticated user cannot access users index', function () {
    $response = $this->get(route('admin.users.index'));
    $response->assertRedirect(route('login'));
});
