<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

function bootstrapFeatureTestCase(): void
{
    test()->seed(\Database\Seeders\TestingBaselineSeeder::class);

    $tenantService = app(\App\Services\TenantService::class);

    \App\Models\Organization::all()->each(function ($organization) use ($tenantService) {
        if (! $tenantService->schemaExists($organization->schema_name)) {
            $tenantService->createSchema($organization->schema_name);
        }
    });
}

function resetTenantSchemas(): void
{
    $tenantService = app(\App\Services\TenantService::class);

    $tenantService->switchToPublic();

    $protectedSchemas = [
        \App\Services\WasteManagementDemoDataService::DEFAULT_SCHEMA_NAME,
    ];

    collect(\Illuminate\Support\Facades\DB::select("
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name LIKE 'tenant\\_%' ESCAPE '\\'
    "))
        ->pluck('schema_name')
        ->reject(fn (string $schemaName): bool => in_array($schemaName, $protectedSchemas, true))
        ->each(function ($schemaName) {
            \Illuminate\Support\Facades\DB::statement(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        });
}

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        bootstrapFeatureTestCase();
    })
    ->in(
        'Feature/Api',
        'Feature/Admin',
        'Feature/Auth',
        'Feature/Feature',
        'Feature/Settings',
        'Feature/AuthorizationServiceTest.php',
        'Feature/DashboardTest.php',
        'Feature/ExampleTest.php',
        'Feature/OrganizationTest.php',
        'Feature/RolesAndPermissionsTest.php',
    );

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->beforeEach(function () {
        resetTenantSchemas();
        bootstrapFeatureTestCase();
    })
    ->in('Feature/WasteManagement');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
