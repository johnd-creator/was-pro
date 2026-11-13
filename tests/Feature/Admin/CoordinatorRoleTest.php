<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoordinatorRoleSeeder;
use Inertia\Testing\AssertableInertia;

test('coordinator is between supervisor and operator with operational but no approval or administrative permissions', function () {
    $coordinator = Role::where('slug', 'coordinator')->firstOrFail();
    $operator = Role::where('slug', 'operator')->firstOrFail();
    $supervisor = Role::where('slug', 'supervisor')->firstOrFail();
    $permissions = $coordinator->permissions->pluck('slug');

    expect($coordinator->name)->toBe('Koordinator')
        ->and($coordinator->level)->toBeGreaterThan($operator->level)->toBeLessThan($supervisor->level)
        ->and($coordinator->is_active)->toBeTrue()
        ->and($permissions->sort()->values()->all())->toBe(collect(CoordinatorRoleSeeder::PERMISSION_SLUGS)->sort()->values()->all())
        ->and($operator->permissions->pluck('slug')->diff($permissions))->toHaveCount(0)
        ->and($permissions->diff($supervisor->permissions->pluck('slug')))->toHaveCount(0)
        ->and($permissions->filter(fn ($slug) => preg_match('/\.(approve|reject|reopen|manage|delete)$/', $slug)))->toHaveCount(0)
        ->and($permissions->filter(fn ($slug) => preg_match('/^(users|roles|organizations|operational_audit|faba_master)\./', $slug)))->toHaveCount(0);
});

test('coordinator installation is additive idempotent and preserves existing role customizations and users', function () {
    $coordinator = Role::where('slug', 'coordinator')->firstOrFail();
    $coordinator->forceDelete();
    $operator = Role::where('slug', 'operator')->firstOrFail();
    $operator->update(['description' => 'Existing custom description']);
    $operator->permissions()->detach(Permission::where('slug', 'faba_reports.export')->value('id'));
    $user = User::factory()->create(['role_id' => $operator->id, 'is_super_admin' => false]);
    $before = Role::with('permissions')->get()->mapWithKeys(fn ($role) => [$role->id => [$role->getAttributes(), $role->permissions->pluck('id')->sort()->values()->all()]])->all();
    $userBefore = $user->fresh()->getAttributes();

    $this->seed(CoordinatorRoleSeeder::class);
    $this->seed(CoordinatorRoleSeeder::class);

    $after = Role::where('slug', '!=', 'coordinator')->with('permissions')->get()->mapWithKeys(fn ($role) => [$role->id => [$role->getAttributes(), $role->permissions->pluck('id')->sort()->values()->all()]])->all();
    expect($after)->toBe($before)
        ->and($user->fresh()->getAttributes())->toBe($userBefore)
        ->and(Role::where('slug', 'coordinator')->count())->toBe(1)
        ->and(User::where('role_id', Role::where('slug', 'coordinator')->value('id'))->count())->toBe(0);
});

test('coordinator installer preserves an existing customized or deleted coordinator', function () {
    $role = Role::where('slug', 'coordinator')->firstOrFail();
    $role->update(['name' => 'Custom Coordinator', 'level' => 35, 'is_active' => false]);
    $role->permissions()->sync(Permission::where('slug', 'dashboard.view')->pluck('id'));
    $this->seed(CoordinatorRoleSeeder::class);
    expect($role->fresh()->name)->toBe('Custom Coordinator')
        ->and($role->fresh()->level)->toBe(35)
        ->and($role->fresh()->is_active)->toBeFalse()
        ->and($role->fresh()->permissions->pluck('slug')->all())->toBe(['dashboard.view']);

    $role->delete();
    $this->seed(CoordinatorRoleSeeder::class);
    expect(Role::where('slug', 'coordinator')->exists())->toBeFalse()
        ->and(Role::withTrashed()->where('slug', 'coordinator')->count())->toBe(1);
});

test('coordinator installation fails without partial role when a required permission is unavailable', function (bool $inactive) {
    Role::where('slug', 'coordinator')->firstOrFail()->forceDelete();
    $permission = Permission::where('slug', 'waste_hauling.submit')->firstOrFail();
    if ($inactive) {
        $permission->update(['is_active' => false]);
    } else {
        $permission->delete();
    }

    expect(fn () => $this->seed(CoordinatorRoleSeeder::class))->toThrow(RuntimeException::class, 'Izin dasar Koordinator belum lengkap');
    expect(Role::withTrashed()->where('slug', 'coordinator')->exists())->toBeFalse();
})->with([false, true]);

test('admin role and user pages list coordinator at its hierarchy level', function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $slugs = ['super_admin', 'admin', 'supervisor', 'coordinator', 'operator'];

    $this->getJson(route('admin.roles.index'))->assertOk()
        ->assertJsonPath('roles.3.slug', 'coordinator')
        ->assertJsonPath('roles.3.level', 40);
    $this->get(route('admin.users.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('admin/Users')->where('roles', fn ($roles) => $roles->pluck('slug')->all() === $slugs));
});
