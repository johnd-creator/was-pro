<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\DisposableDatabase::verify($app);

foreach ([Database\Seeders\RolesSeeder::class, Database\Seeders\PermissionsSeeder::class, Database\Seeders\RolePermissionsSeeder::class] as $seeder) {
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
}
$organization = App\Models\Organization::firstOrCreate(['code' => 'MOBILE_UAT'], [
    'name' => 'Organisasi Uji Mobile', 'schema_name' => 'tenant_mobile_uat', 'is_active' => true,
]);
$tenant = app(App\Services\TenantService::class);
if (! $tenant->schemaExists($organization->schema_name)) {
    $tenant->createSchema($organization->schema_name);
}
$tenant->switchToSchema($organization->schema_name);
$tenant->runMigrationsForTenant($organization->schema_name, 'database/migrations/tenant');
$tenant->switchToPublic();
foreach (['operator', 'supervisor-a', 'supervisor-b'] as $name) {
    $role = App\Models\Role::where('slug', $name === 'operator' ? 'operator' : 'supervisor')->firstOrFail();
    App\Models\User::updateOrCreate(['email' => $name.'@mobile.test'], [
        'name' => $name, 'organization_id' => $organization->id, 'role_id' => $role->id,
        'password' => Illuminate\Support\Facades\Hash::make('mobile-fixture-only'),
        'email_verified_at' => now(),
    ]);
}
$tenant->switchToSchema($organization->schema_name);
$category = App\Models\WasteCategory::firstOrCreate(['code' => 'MOBILE'], ['name' => 'Kategori Uji Mobile', 'is_active' => true]);
$characteristic = App\Models\WasteCharacteristic::firstOrCreate(['code' => 'MOBILE'], ['name' => 'Karakteristik Uji Mobile', 'is_active' => true, 'is_hazardous' => false]);
App\Models\WasteType::firstOrCreate(['code' => 'MOBILE'], [
    'name' => 'Limbah Uji Mobile', 'category_id' => $category->id, 'characteristic_id' => $characteristic->id,
    'storage_period_days' => 90, 'transport_cost' => 0, 'is_active' => true,
]);
$tenant->switchToPublic();
echo "Disposable mobile fixture ready.\n";
