<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

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

function usersListOrganizationFixture(): array
{
    $organizationA = Organization::factory()->create(['code' => 'LISTALPHA', 'schema_name' => 'tenant_users_list_alpha']);
    $organizationB = Organization::factory()->create(['code' => 'LISTBETA', 'schema_name' => 'tenant_users_list_beta']);
    foreach ([$organizationA, $organizationB] as $organization) {
        app(\App\Services\TenantService::class)->createSchema($organization->schema_name);
    }
    $supervisorRole = Role::where('slug', 'supervisor')->firstOrFail();
    $superAdmin = User::factory()->create(['organization_id' => $organizationA->id, 'is_super_admin' => true]);
    $supervisorA = User::factory()->create(['organization_id' => $organizationA->id, 'role_id' => $supervisorRole->id, 'is_super_admin' => false]);
    $supervisorB = User::factory()->create(['organization_id' => $organizationB->id, 'role_id' => $supervisorRole->id, 'is_super_admin' => false]);
    $operatorB = User::factory()->create(['organization_id' => $organizationB->id, 'role_id' => Role::where('slug', 'operator')->firstOrFail()->id, 'is_super_admin' => false]);

    return compact('organizationA', 'organizationB', 'superAdmin', 'supervisorA', 'supervisorB', 'operatorB');
}

test('assigned superadmin receives foreign organization users with exact filter IDs', function () {
    $fixture = usersListOrganizationFixture();
    $expected = collect([$fixture['supervisorB']->id, $fixture['operatorB']->id])->sort()->values()->all();

    $this->actingAs($fixture['superAdmin'])->get(route('admin.users.index'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('admin/Users')
        ->has('users', 4)
        ->where('users', function ($users) use ($fixture, $expected): bool {
            $matching = collect($users)->filter(fn ($user): bool => data_get($user, 'organization_id') === (string) $fixture['organizationB']->id);

            return $matching->pluck('id')->sort()->values()->all() === $expected
                && $matching->every(fn ($user): bool => data_get($user, 'organization.id') === (string) $fixture['organizationB']->id);
        }));
});

test('ordinary supervisor Users list stays within assigned organization despite foreign query filter', function (string $own, string $foreign, string $actor) {
    $fixture = usersListOrganizationFixture();

    $this->actingAs($fixture[$actor])->get(route('admin.users.index', ['organization_id' => $fixture[$foreign]->id]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('users', 2)
        ->where('users', fn ($users): bool => collect($users)->every(fn ($user): bool => data_get($user, 'organization_id') === (string) $fixture[$own]->id)));
})->with([
    ['organizationA', 'organizationB', 'supervisorA'],
    ['organizationB', 'organizationA', 'supervisorB'],
]);

test('unassigned ordinary supervisor cannot receive a global Users list', function () {
    $user = User::factory()->create(['organization_id' => null, 'role_id' => Role::where('slug', 'supervisor')->firstOrFail()->id, 'is_super_admin' => false]);

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('OrganizationRequired')->missing('users'));
});

test('superadmin create and promotion always detach organization', function (bool $update) {
    $actor = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    $organization = Organization::factory()->create();
    $target = User::factory()->create(['organization_id' => $organization->id, 'is_super_admin' => false]);
    $data = ['name' => 'Global Admin', 'email' => $update ? $target->email : 'global@example.com', 'role_id' => Role::factory()->create()->id, 'organization_id' => $organization->id, 'is_super_admin' => true, 'password' => 'password123', 'password_confirmation' => 'password123'];
    $this->actingAs($actor);
    ($update ? $this->put(route('admin.users.update', $target), $data) : $this->post(route('admin.users.store'), $data))->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
    $saved = User::where('email', $data['email'])->firstOrFail();
    expect($saved->is_super_admin)->toBeTrue()->and($saved->organization_id)->toBeNull();
})->with([false, true]);

test('ordinary accounts require active organization on create and demotion', function (bool $update, string $state) {
    $actor = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    $target = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    $organizationId = null;
    if ($state !== 'missing') {
        $organization = Organization::factory()->create(['is_active' => $state !== 'inactive']);
        $organizationId = $organization->id;
        if ($state === 'deleted') {
            $organization->delete();
        }
    }
    $data = ['name' => 'Ordinary', 'email' => $update ? $target->email : 'ordinary@example.com', 'role_id' => Role::factory()->create()->id, 'organization_id' => $organizationId, 'is_super_admin' => false, 'password' => 'password123', 'password_confirmation' => 'password123'];
    $this->actingAs($actor);
    ($update ? $this->put(route('admin.users.update', $target), $data) : $this->post(route('admin.users.store'), $data))->assertSessionHasErrors('organization_id');
    expect($target->fresh()->is_super_admin)->toBeTrue();
})->with([false, true])->with(['missing', 'inactive', 'deleted']);

test('omitted superadmin status is preserved while legacy organization is detached', function () {
    $actor = User::factory()->create(['is_super_admin' => true]);
    $target = User::factory()->create(['is_super_admin' => true, 'organization_id' => Organization::factory()->create()->id]);
    $this->actingAs($actor)->put(route('admin.users.update', $target), ['name' => $target->name, 'email' => $target->email, 'role_id' => Role::factory()->create()->id])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->is_super_admin)->toBeTrue()->and($target->fresh()->organization_id)->toBeNull();
});

function privilegedOrdinaryUserFixture(): array
{
    $organization = Organization::factory()->create();
    app(\App\Services\TenantService::class)->createSchema($organization->schema_name);
    $role = Role::factory()->create();
    $role->permissions()->sync(\App\Models\Permission::query()->pluck('id'));
    $actor = User::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id, 'is_super_admin' => false]);

    return [$actor, $organization, $role];
}

test('ordinary user manager cannot grant superadmin or mutate existing superadmins', function (string $action) {
    [$actor, $organization, $role] = privilegedOrdinaryUserFixture();
    $target = User::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id, 'is_super_admin' => $action !== 'promote']);
    $data = ['name' => 'Changed', 'email' => $target->email, 'role_id' => $role->id, 'organization_id' => $organization->id, 'is_super_admin' => $action !== 'demote', 'password' => 'password123', 'password_confirmation' => 'password123'];
    $this->actingAs($actor);
    $response = match ($action) {
        'create' => $this->post(route('admin.users.store'), [...$data, 'email' => 'escalation@example.com']),
        'delete' => $this->delete(route('admin.users.destroy', $target)),
        'password' => $this->put(route('admin.users.update-password', $target), $data),
        default => $this->put(route('admin.users.update', $target), $data),
    };
    $response->assertForbidden();
    expect($target->fresh()->name)->toBe($target->name)->and($target->fresh()->is_super_admin)->toBe($target->is_super_admin)->and($target->fresh()->password)->toBe($target->password);
    $this->assertDatabaseMissing('users', ['email' => 'escalation@example.com']);
})->with(['create', 'promote', 'edit', 'demote', 'password', 'delete']);

test('ordinary manager cannot create or move users across organizations', function (string $action) {
    [$actor, $organization, $role] = privilegedOrdinaryUserFixture();
    $foreign = Organization::factory()->create();
    $target = User::factory()->create(['organization_id' => $action === 'foreign-update' ? $foreign->id : $organization->id, 'role_id' => $role->id, 'is_super_admin' => false]);
    $data = ['name' => 'Changed', 'email' => $action === 'create' ? 'foreign@example.com' : $target->email, 'role_id' => $role->id, 'organization_id' => $action === 'foreign-update' ? $organization->id : $foreign->id, 'is_super_admin' => false, 'password' => 'password123', 'password_confirmation' => 'password123'];
    $this->actingAs($actor);
    ($action === 'create' ? $this->post(route('admin.users.store'), $data) : $this->put(route('admin.users.update', $target), $data))->assertForbidden();
    expect($target->fresh()->organization_id)->toBe($target->organization_id);
})->with(['create', 'move', 'foreign-update']);

test('ordinary manager can update an ordinary user in own organization', function () {
    [$actor, $organization, $role] = privilegedOrdinaryUserFixture();
    $target = User::factory()->create(['organization_id' => $organization->id, 'role_id' => $role->id, 'is_super_admin' => false]);
    $this->actingAs($actor)->put(route('admin.users.update', $target), ['name' => 'Allowed', 'email' => $target->email, 'role_id' => $role->id, 'organization_id' => $organization->id, 'is_super_admin' => false])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->name)->toBe('Allowed');
});
