<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');

        // Super Admin - All permissions
        if ($roles->has('super_admin')) {
            $superAdmin = $roles->get('super_admin');
            $allPermissions = Permission::pluck('id');
            $superAdmin->permissions()->sync($allPermissions);
            $this->command->info('Super Admin permissions assigned.');
        }

        // Admin - All permissions except organization management
        if ($roles->has('admin')) {
            $admin = $roles->get('admin');
            $adminPermissions = Permission::where('module', '!=', 'organizations')->pluck('id');
            $admin->permissions()->sync($adminPermissions);
            $this->command->info('Admin permissions assigned.');
        }

        // Supervisor - Master data CRUD, View all records, Approve/reject, Transportation CRUD
        if ($roles->has('supervisor')) {
            $supervisor = $roles->get('supervisor');
            $supervisorPermissionSlugs = [
                // Master data
                'waste_categories.view', 'waste_categories.create', 'waste_categories.edit',
                'waste_characteristics.view', 'waste_characteristics.create', 'waste_characteristics.edit',
                'waste_types.view', 'waste_types.create', 'waste_types.edit',
                'vendors.view', 'vendors.create', 'vendors.edit',
                // Waste records
                'waste_records.view_all', 'waste_records.view_own', 'waste_records.create', 'waste_records.edit_own',
                'waste_records.approve', 'waste_records.reject', 'waste_records.submit',
                // Waste hauling
                'waste_hauling.view_all', 'waste_hauling.view_own', 'waste_hauling.create', 'waste_hauling.submit',
                'waste_hauling.approve', 'waste_hauling.reject', 'waste_hauling.cancel',
                // Dashboard
                'dashboard.view',
                'faba_dashboard.view',
                'faba_production.view', 'faba_production.create', 'faba_production.edit',
                'faba_utilization.view', 'faba_utilization.create', 'faba_utilization.edit',
                'faba_adjustments.view', 'faba_adjustments.create', 'faba_adjustments.edit', 'faba_adjustments.delete',
                'faba_recaps.view', 'faba_opening_balance.manage',
                'faba_approvals.view', 'faba_approvals.submit', 'faba_approvals.approve', 'faba_approvals.reject', 'faba_approvals.reopen',
                'faba_reports.export',
                // Users (view only)
                'users.view',
            ];
            $supervisorPermissions = Permission::whereIn('slug', $supervisorPermissionSlugs)->pluck('id');
            $supervisor->permissions()->sync($supervisorPermissions);
            $this->command->info('Supervisor permissions assigned.');
        }

        // Operator - View own records, Create/edit own, Transportation CRUD
        if ($roles->has('operator')) {
            $operator = $roles->get('operator');
            $operatorPermissionSlugs = [
                // Waste records (own only)
                'waste_records.view_all', 'waste_records.view_own', 'waste_records.create', 'waste_records.edit_own', 'waste_records.submit',
                // Waste hauling
                'waste_hauling.view_own', 'waste_hauling.create', 'waste_hauling.submit', 'waste_hauling.cancel',
                // Dashboard
                'dashboard.view',
                'faba_dashboard.view',
                'faba_production.view', 'faba_production.create', 'faba_production.edit',
                'faba_utilization.view', 'faba_utilization.create', 'faba_utilization.edit',
                'faba_adjustments.view', 'faba_adjustments.create', 'faba_adjustments.edit',
                'faba_recaps.view',
                'faba_approvals.view', 'faba_approvals.submit',
                'faba_reports.export',
                // View only for master data
                'waste_categories.view',
                'waste_characteristics.view',
                'waste_types.view',
                'vendors.view',
            ];
            $operatorPermissions = Permission::whereIn('slug', $operatorPermissionSlugs)->pluck('id');
            $operator->permissions()->sync($operatorPermissions);
            $this->command->info('Operator permissions assigned.');
        }

        // Ensure roles.view/manage are assigned to appropriate roles
        if ($roles->has('admin')) {
            $admin = $roles->get('admin');
            $extra = Permission::whereIn('slug', ['roles.view', 'roles.manage'])->pluck('id');
            $admin->permissions()->syncWithoutDetaching($extra);
        }

        if ($roles->has('supervisor')) {
            $supervisor = $roles->get('supervisor');
            $viewOnly = Permission::whereIn('slug', ['roles.view'])->pluck('id');
            $supervisor->permissions()->syncWithoutDetaching($viewOnly);
        }

        $this->command->info('Role permissions seeded successfully.');
    }
}
