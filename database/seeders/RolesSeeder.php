<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Super administrator with access to all organizations and full system permissions.',
                'level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Organization administrator with full permissions within their organization.',
                'level' => 75,
                'is_active' => true,
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'description' => 'Supervisor who can manage master data, view all records, and approve/reject waste records.',
                'level' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Operator',
                'slug' => 'operator',
                'description' => 'Operator who can create and edit their own waste records and manage transportation.',
                'level' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }

        $this->command->info('Roles seeded successfully.');
    }
}
