<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

test('organization admins cannot change globally shared role permissions', function (string $slug) {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    app(\App\Services\TenantService::class)->createSchema($organizationA->schema_name);
    $actorRole = Role::where('slug', $slug)->firstOrFail();
    $actorRole->permissions()->syncWithoutDetaching(Permission::where('slug', 'roles.manage')->firstOrFail());
    $actor = User::factory()->create(['organization_id' => $organizationA->id, 'role_id' => $actorRole->id, 'is_super_admin' => false]);
    $sharedRole = Role::where('slug', 'operator')->firstOrFail();
    $otherUser = User::factory()->create(['organization_id' => $organizationB->id, 'role_id' => $sharedRole->id]);
    $before = $sharedRole->permissions()->pluck('permissions.id')->sort()->values()->all();

    $this->actingAs($actor)->putJson(route('admin.roles.update', $sharedRole), ['permissions' => []])->assertForbidden();

    expect($otherUser->fresh()->role->permissions->pluck('id')->sort()->values()->all())->toBe($before);
    $this->getJson(route('admin.roles.index'))->assertOk()->assertJsonPath('can_manage', false);
})->with(['admin', 'super_admin']);

test('only the true super admin flag enables global role editing', function () {
    $actor = User::factory()->create(['is_super_admin' => true, 'role_id' => null]);
    $target = Role::factory()->create();
    $permission = Permission::where('slug', 'roles.view')->firstOrFail();

    $this->actingAs($actor)->getJson(route('admin.roles.index'))->assertOk()->assertJsonPath('can_manage', true);
    $this->putJson(route('admin.roles.update', $target), ['permissions' => [$permission->id]])->assertOk();
    expect($target->fresh()->permissions->pluck('id')->all())->toBe([$permission->id]);
    $this->putJson(route('admin.roles.update', $target), ['permissions' => ['not-a-uuid']])->assertUnprocessable();
    expect($target->fresh()->permissions->pluck('id')->all())->toBe([$permission->id]);
});
