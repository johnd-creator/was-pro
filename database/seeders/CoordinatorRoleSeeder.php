<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Adds the coordinator role without changing existing roles or user assignments. */
class CoordinatorRoleSeeder extends Seeder
{
    public const PERMISSION_SLUGS = [
        'waste_records.view_all', 'waste_records.view_own', 'waste_records.create', 'waste_records.edit_own', 'waste_records.submit',
        'waste_hauling.view_all', 'waste_hauling.view_own', 'waste_hauling.create', 'waste_hauling.submit', 'waste_hauling.cancel',
        'dashboard.view',
        'faba_dashboard.view',
        'faba_production.view', 'faba_production.create', 'faba_production.edit',
        'faba_utilization.view', 'faba_utilization.create', 'faba_utilization.edit',
        'faba_adjustments.view', 'faba_adjustments.create', 'faba_adjustments.edit',
        'faba_recaps.view', 'faba_approvals.view', 'faba_approvals.submit', 'faba_reports.export',
        'waste_categories.view', 'waste_characteristics.view', 'waste_types.view', 'vendors.view',
    ];

    public function run(): void
    {
        (new Role)->getConnection()->transaction(function (): void {
            if (Role::withTrashed()->where('slug', 'coordinator')->exists()) {
                $this->command?->info('Peran Koordinator sudah ada; pengaturan dan akses yang ada dipertahankan.');

                return;
            }

            $permissions = Permission::query()->active()->whereIn('slug', self::PERMISSION_SLUGS)->pluck('id', 'slug');
            if ($permissions->count() !== count(self::PERMISSION_SLUGS)) {
                throw new RuntimeException('Izin dasar Koordinator belum lengkap. Tidak ada peran atau akses yang diubah.');
            }

            $role = Role::query()->create([
                'name' => 'Koordinator',
                'slug' => 'coordinator',
                'description' => 'Koordinator operasional di bawah Supervisor dan di atas Operator. Menyusun dan mengajukan rencana pengangkutan dalam organisasi sendiri, tanpa hak persetujuan atau administrasi.',
                'level' => 40,
                'is_active' => true,
            ]);
            $role->permissions()->sync($permissions->values());

            $this->command?->info('Peran Koordinator dibuat tanpa mengubah peran atau penugasan pengguna yang sudah ada.');
        });
    }
}
