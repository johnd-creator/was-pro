<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestingBaselineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            RolePermissionsSeeder::class,
        ]);
    }
}
