<?php

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('mobile login returns bearer token and user bootstrap payload', function () {
    $organization = Organization::factory()->create([
        'code' => 'MOBILE',
        'schema_name' => 'tenant_mobile',
    ]);
    $role = Role::query()->firstOrCreate(
        ['slug' => 'operator'],
        [
            'name' => 'Operator',
            'description' => 'Operator role',
            'level' => 1,
            'is_active' => true,
        ],
    );
    $permission = Permission::query()->firstOrCreate(
        ['slug' => 'waste_records.create'],
        [
            'name' => 'Create Waste Record',
            'module' => 'waste_records',
            'description' => 'Create waste record permission',
            'is_active' => true,
        ],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $role->id,
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'pixel-test',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.context.user.id', $user->id)
        ->assertJsonPath('data.context.organization.id', $organization->id);

    expect(ApiToken::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('authenticated mobile me endpoint returns permissions and organization context', function () {
    $organization = Organization::factory()->create([
        'code' => 'MOBILE',
        'schema_name' => 'tenant_mobile_me',
    ]);
    $role = Role::query()->firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'description' => 'Supervisor role',
            'level' => 2,
            'is_active' => true,
        ],
    );
    $permission = Permission::query()->firstOrCreate(
        ['slug' => 'faba_approvals.approve'],
        [
            'name' => 'Approve FABA',
            'module' => 'faba_approvals',
            'description' => 'Approve FABA monthly approval',
            'is_active' => true,
        ],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $role->id,
    ]);

    $plainTextToken = 'mobile-test-token';
    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'test-device',
        'token' => hash('sha256', $plainTextToken),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->withToken($plainTextToken)
        ->getJson('/api/v1/auth/me');

    $response->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.organization.id', $organization->id)
        ->assertJsonFragment(['faba_approvals.approve']);
});

test('mobile logout deletes the current api token', function () {
    $user = User::factory()->create();
    $plainTextToken = 'logout-token';
    $token = ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'logout-device',
        'token' => hash('sha256', $plainTextToken),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->withToken($plainTextToken)
        ->postJson('/api/v1/auth/logout');

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    expect(ApiToken::query()->find($token->id))->toBeNull();
});
