<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Organization permissions
            ['name' => 'View Organizations', 'slug' => 'organizations.view', 'module' => 'organizations', 'description' => 'View organization details'],
            ['name' => 'Create Organizations', 'slug' => 'organizations.create', 'module' => 'organizations', 'description' => 'Create new organizations'],
            ['name' => 'Edit Organizations', 'slug' => 'organizations.edit', 'module' => 'organizations', 'description' => 'Edit organization details'],
            ['name' => 'Delete Organizations', 'slug' => 'organizations.delete', 'module' => 'organizations', 'description' => 'Delete organizations'],

            // User permissions
            ['name' => 'View Users', 'slug' => 'users.view', 'module' => 'users', 'description' => 'View user list and details'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'module' => 'users', 'description' => 'Create new users'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'module' => 'users', 'description' => 'Edit user details'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'module' => 'users', 'description' => 'Delete users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'users', 'description' => 'Manage users in organization'],

            // Waste category permissions
            ['name' => 'View Waste Categories', 'slug' => 'waste_categories.view', 'module' => 'waste_categories', 'description' => 'View waste categories'],
            ['name' => 'Create Waste Categories', 'slug' => 'waste_categories.create', 'module' => 'waste_categories', 'description' => 'Create waste categories'],
            ['name' => 'Edit Waste Categories', 'slug' => 'waste_categories.edit', 'module' => 'waste_categories', 'description' => 'Edit waste categories'],
            ['name' => 'Delete Waste Categories', 'slug' => 'waste_categories.delete', 'module' => 'waste_categories', 'description' => 'Delete waste categories'],

            // Waste characteristic permissions
            ['name' => 'View Waste Characteristics', 'slug' => 'waste_characteristics.view', 'module' => 'waste_characteristics', 'description' => 'View waste characteristics'],
            ['name' => 'Create Waste Characteristics', 'slug' => 'waste_characteristics.create', 'module' => 'waste_characteristics', 'description' => 'Create waste characteristics'],
            ['name' => 'Edit Waste Characteristics', 'slug' => 'waste_characteristics.edit', 'module' => 'waste_characteristics', 'description' => 'Edit waste characteristics'],
            ['name' => 'Delete Waste Characteristics', 'slug' => 'waste_characteristics.delete', 'module' => 'waste_characteristics', 'description' => 'Delete waste characteristics'],

            // Waste type permissions
            ['name' => 'View Waste Types', 'slug' => 'waste_types.view', 'module' => 'waste_types', 'description' => 'View waste types'],
            ['name' => 'Create Waste Types', 'slug' => 'waste_types.create', 'module' => 'waste_types', 'description' => 'Create waste types'],
            ['name' => 'Edit Waste Types', 'slug' => 'waste_types.edit', 'module' => 'waste_types', 'description' => 'Edit waste types'],
            ['name' => 'Delete Waste Types', 'slug' => 'waste_types.delete', 'module' => 'waste_types', 'description' => 'Delete waste types'],

            // Vendor permissions
            ['name' => 'View Vendors', 'slug' => 'vendors.view', 'module' => 'vendors', 'description' => 'View vendors'],
            ['name' => 'Create Vendors', 'slug' => 'vendors.create', 'module' => 'vendors', 'description' => 'Create vendors'],
            ['name' => 'Edit Vendors', 'slug' => 'vendors.edit', 'module' => 'vendors', 'description' => 'Edit vendors'],
            ['name' => 'Delete Vendors', 'slug' => 'vendors.delete', 'module' => 'vendors', 'description' => 'Delete vendors'],

            // Waste record permissions
            ['name' => 'View All Waste Records', 'slug' => 'waste_records.view_all', 'module' => 'waste_records', 'description' => 'View all waste records in organization'],
            ['name' => 'View Own Waste Records', 'slug' => 'waste_records.view_own', 'module' => 'waste_records', 'description' => 'View own waste records'],
            ['name' => 'Create Waste Records', 'slug' => 'waste_records.create', 'module' => 'waste_records', 'description' => 'Create waste records'],
            ['name' => 'Edit Own Waste Records', 'slug' => 'waste_records.edit_own', 'module' => 'waste_records', 'description' => 'Edit own waste records'],
            ['name' => 'Edit All Waste Records', 'slug' => 'waste_records.edit_all', 'module' => 'waste_records', 'description' => 'Edit all waste records'],
            ['name' => 'Delete Waste Records', 'slug' => 'waste_records.delete', 'module' => 'waste_records', 'description' => 'Delete waste records'],
            ['name' => 'Approve Waste Records', 'slug' => 'waste_records.approve', 'module' => 'waste_records', 'description' => 'Approve waste records'],
            ['name' => 'Reject Waste Records', 'slug' => 'waste_records.reject', 'module' => 'waste_records', 'description' => 'Reject waste records'],
            ['name' => 'Submit Waste Records', 'slug' => 'waste_records.submit', 'module' => 'waste_records', 'description' => 'Submit waste records for approval'],

            // Waste transportation permissions
            ['name' => 'View All Transportation', 'slug' => 'transportation.view_all', 'module' => 'transportation', 'description' => 'View all transportation records'],
            ['name' => 'View Own Transportation', 'slug' => 'transportation.view_own', 'module' => 'transportation', 'description' => 'View own transportation records'],
            ['name' => 'Create Transportation', 'slug' => 'transportation.create', 'module' => 'transportation', 'description' => 'Create waste transportation'],
            ['name' => 'Edit Transportation', 'slug' => 'transportation.edit', 'module' => 'transportation', 'description' => 'Edit waste transportation'],
            ['name' => 'Delete Transportation', 'slug' => 'transportation.delete', 'module' => 'transportation', 'description' => 'Delete waste transportation'],

            // Dashboard permissions
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard', 'description' => 'View dashboard and statistics'],

            // FABA permissions
            ['name' => 'View FABA Dashboard', 'slug' => 'faba_dashboard.view', 'module' => 'faba_dashboard', 'description' => 'View FABA dashboard'],
            ['name' => 'View FABA Production', 'slug' => 'faba_production.view', 'module' => 'faba_production', 'description' => 'View FABA production entries'],
            ['name' => 'Create FABA Production', 'slug' => 'faba_production.create', 'module' => 'faba_production', 'description' => 'Create FABA production entries'],
            ['name' => 'Edit FABA Production', 'slug' => 'faba_production.edit', 'module' => 'faba_production', 'description' => 'Edit FABA production entries'],
            ['name' => 'Delete FABA Production', 'slug' => 'faba_production.delete', 'module' => 'faba_production', 'description' => 'Delete FABA production entries'],
            ['name' => 'View FABA Utilization', 'slug' => 'faba_utilization.view', 'module' => 'faba_utilization', 'description' => 'View FABA utilization entries'],
            ['name' => 'Create FABA Utilization', 'slug' => 'faba_utilization.create', 'module' => 'faba_utilization', 'description' => 'Create FABA utilization entries'],
            ['name' => 'Edit FABA Utilization', 'slug' => 'faba_utilization.edit', 'module' => 'faba_utilization', 'description' => 'Edit FABA utilization entries'],
            ['name' => 'Delete FABA Utilization', 'slug' => 'faba_utilization.delete', 'module' => 'faba_utilization', 'description' => 'Delete FABA utilization entries'],
            ['name' => 'View FABA Recaps', 'slug' => 'faba_recaps.view', 'module' => 'faba_recaps', 'description' => 'View FABA recaps'],
            ['name' => 'View FABA Approvals', 'slug' => 'faba_approvals.view', 'module' => 'faba_approvals', 'description' => 'View FABA approvals'],
            ['name' => 'Submit FABA Approvals', 'slug' => 'faba_approvals.submit', 'module' => 'faba_approvals', 'description' => 'Submit FABA monthly approvals'],
            ['name' => 'Approve FABA Approvals', 'slug' => 'faba_approvals.approve', 'module' => 'faba_approvals', 'description' => 'Approve FABA monthly approvals'],
            ['name' => 'Reject FABA Approvals', 'slug' => 'faba_approvals.reject', 'module' => 'faba_approvals', 'description' => 'Reject FABA monthly approvals'],
            ['name' => 'Reopen FABA Approvals', 'slug' => 'faba_approvals.reopen', 'module' => 'faba_approvals', 'description' => 'Reopen approved FABA periods'],
            ['name' => 'Export FABA Reports', 'slug' => 'faba_reports.export', 'module' => 'faba_reports', 'description' => 'Export FABA reports'],

            // Roles & Permissions
            ['name' => 'View Roles', 'slug' => 'roles.view', 'module' => 'roles', 'description' => 'View roles and permissions'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'roles', 'description' => 'Assign permissions to roles'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $this->command->info('Permissions seeded successfully.');
    }
}
