<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/** Registers opt-in permissions only; never assigns roles. */
class OperationalToolsPermissionsSeeder extends Seeder
{
    public const SLUGS = ['faba_master.view', 'faba_master.create', 'faba_master.edit', 'operational_audit.view'];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'operational_tools', 'description' => 'Akses tambahan yang harus diberikan secara eksplisit.']);
        }
    }
}
