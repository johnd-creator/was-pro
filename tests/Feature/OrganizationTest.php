<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantService;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;

beforeEach(function () {
    // Switch to public schema before each test
    app(TenantService::class)->switchToPublic();
});

test('organization can be created', function () {
    $organization = Organization::factory()->create();

    expect($organization->id)->not->toBeNull();
    assertModelExists($organization);
    assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => $organization->name,
        'code' => $organization->code,
    ]);
});

test('organization has unique code', function () {
    $organization = Organization::factory()->create();

    expect(fn () => Organization::factory()->create(['code' => $organization->code]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('organization has unique schema name', function () {
    $organization = Organization::factory()->create();

    expect(fn () => Organization::factory()->create(['schema_name' => $organization->schema_name]))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('organization can have users', function () {
    $organization = Organization::factory()->create();

    $user = User::factory()->for($organization)->create();

    expect($organization->users)->toHaveCount(1);
    expect($user->organization->id)->toBe($organization->id);
});

test('organization scope active returns only active organizations', function () {
    Organization::factory()->create(['is_active' => true]);
    Organization::factory()->create(['is_active' => false]);

    $activeOrganizations = Organization::active()->get();

    expect($activeOrganizations)->toHaveCount(1);
    expect($activeOrganizations->first()->is_active)->toBeTrue();
});

test('organization schema can be created', function () {
    $tenantService = app(TenantService::class);
    $schemaName = 'test_schema_'.time();

    $tenantService->createSchema($schemaName);

    expect($tenantService->schemaExists($schemaName))->toBeTrue();

    // Cleanup
    $tenantService->dropSchema($schemaName);
});

test('organization schema can be dropped', function () {
    $tenantService = app(TenantService::class);
    $schemaName = 'test_schema_'.time();

    $tenantService->createSchema($schemaName);
    expect($tenantService->schemaExists($schemaName))->toBeTrue();

    $tenantService->dropSchema($schemaName);
    expect($tenantService->schemaExists($schemaName))->toBeFalse();
});

test('tenant service can switch schemas', function () {
    $tenantService = app(TenantService::class);
    $schemaName = 'test_schema_'.time();

    $tenantService->createSchema($schemaName);

    $tenantService->switchToSchema($schemaName);
    expect($tenantService->getCurrentSchema())->toBe($schemaName);

    $tenantService->switchToPublic();
    expect($tenantService->getCurrentSchema())->toBe('public');

    // Cleanup
    $tenantService->dropSchema($schemaName);
});
