<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\WasteType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            RolePermissionsSeeder::class,
        ]);

        // Create test organization for testing
        $this->createTestOrganization();

        // Create test users
        $this->createTestUsers();

        // Create waste types for testing
        $this->createWasteTypes();
    }

    private function createTestOrganization(): void
    {
        Organization::firstOrCreate(
            ['code' => 'TWMS'],
            [
                'name' => 'Test Waste Management System',
                'schema_name' => 'tenant_twms',
                'address' => 'Test Address',
                'phone' => '1234567890',
                'email' => 'admin@twms.com',
            ]
        );
    }

    private function createTestUsers(): void
    {
        $org = Organization::where('code', 'TWMS')->first();

        if (! $org) {
            return;
        }

        // Get role IDs (note: slugs use underscores, not hyphens)
        $superAdminRole = \App\Models\Role::where('slug', 'super_admin')->first();
        $supervisorRole = \App\Models\Role::where('slug', 'supervisor')->first();
        $operatorRole = \App\Models\Role::where('slug', 'operator')->first();

        // Only create users if roles exist
        if (! $superAdminRole || ! $supervisorRole || ! $operatorRole) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'super@testwms.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'organization_id' => $org->id,
                'role_id' => $superAdminRole->id,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'supervisor@testwms.com'],
            [
                'name' => 'Supervisor',
                'password' => bcrypt('password'),
                'organization_id' => $org->id,
                'role_id' => $supervisorRole->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'operator@testwms.com'],
            [
                'name' => 'Operator',
                'password' => bcrypt('password'),
                'organization_id' => $org->id,
                'role_id' => $operatorRole->id,
                'email_verified_at' => now(),
            ]
        );

        // Add john@d.co user
        User::firstOrCreate(
            ['email' => 'john@d.co'],
            [
                'name' => 'John',
                'password' => bcrypt('password'),
                'organization_id' => $org->id,
                'role_id' => $superAdminRole->id,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function createWasteTypes(): void
    {
        $tenantService = app(\App\Services\TenantService::class);
        $org = Organization::where('code', 'TWMS')->first();

        if (! $org) {
            return;
        }

        // Ensure schema exists
        if (! $tenantService->schemaExists($org->schema_name)) {
            $tenantService->createSchema($org->schema_name);
        }

        // Switch to tenant schema
        $tenantService->switchToSchema($org->schema_name);

        // Check if waste_types table exists (tenant migrations might need to be run manually)
        if (! \Schema::hasTable('waste_types')) {
            // Switch back to public schema and return
            $tenantService->switchToPublic();

            return;
        }

        // Create waste categories first
        $organicCategory = \App\Models\WasteCategory::firstOrCreate(
            ['code' => 'ORG'],
            [
                'name' => 'Organic',
                'description' => 'Organic waste materials',
            ]
        );

        $plasticCategory = \App\Models\WasteCategory::firstOrCreate(
            ['code' => 'PLA'],
            [
                'name' => 'Plastic',
                'description' => 'Plastic waste materials',
            ]
        );

        $paperCategory = \App\Models\WasteCategory::firstOrCreate(
            ['code' => 'PAP'],
            [
                'name' => 'Paper',
                'description' => 'Paper and cardboard waste',
            ]
        );

        // Create waste characteristics
        $generalCharacteristic = \App\Models\WasteCharacteristic::firstOrCreate(
            ['code' => 'GEN'],
            [
                'name' => 'General',
                'description' => 'General waste characteristics',
            ]
        );

        // Create waste types with required category and characteristic
        WasteType::firstOrCreate(
            ['code' => 'ORG-001'],
            [
                'name' => 'Organic Waste',
                'category_id' => $organicCategory->id,
                'characteristic_id' => $generalCharacteristic->id,
                'storage_period_days' => 7,
                'transport_cost' => 100000,
                'description' => 'Organic waste materials',
            ]
        );

        WasteType::firstOrCreate(
            ['code' => 'PLA-001'],
            [
                'name' => 'Plastic Waste',
                'category_id' => $plasticCategory->id,
                'characteristic_id' => $generalCharacteristic->id,
                'storage_period_days' => 30,
                'transport_cost' => 50000,
                'description' => 'Plastic waste materials',
            ]
        );

        WasteType::firstOrCreate(
            ['code' => 'PAP-001'],
            [
                'name' => 'Paper Waste',
                'category_id' => $paperCategory->id,
                'characteristic_id' => $generalCharacteristic->id,
                'storage_period_days' => 14,
                'transport_cost' => 75000,
                'description' => 'Paper and cardboard waste',
            ]
        );

        // Switch back to public schema
        $tenantService->switchToPublic();
    }
}
