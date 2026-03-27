<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('organizationName')
            ->has('notificationCount')
            ->has('headerRiskLabel')
            ->has('headerRiskTone')
            ->has('stats')
            ->has('pendingApprovals')
            ->has('wasteByCategory')
            ->has('transportationByStatus')
            ->has('fabaStats')
            ->has('fabaChart', 6)
            ->has('wasteChart', 6)
            ->has('notificationSummary')
            ->has('header')
        );
});

test('dashboard reads movement-based faba data from migrated tenant schema', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHFABA',
        'schema_name' => 'tenant_dashboard_faba_ready',
    ]);

    $tenantService = app(TenantService::class);

    if (! $tenantService->schemaExists($organization->schema_name)) {
        $tenantService->createSchema($organization->schema_name);
    }

    $tenantService->switchToSchema($organization->schema_name);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('fabaChart', 6)
            ->has('fabaStats')
        );

    $tenantService->switchToPublic();
});
