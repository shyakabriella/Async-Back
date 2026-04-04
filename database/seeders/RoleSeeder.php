<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'System administrator with full access',
                'is_active' => true,
            ],
            [
                'name' => 'Ceo',
                'slug' => 'ceo',
                'description' => 'Chief Executive Officer',
                'is_active' => true,
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Student Account',
                'is_active' => true,
            ],
            [
                'name' => 'Trainer',
                'slug' => 'trainer',
                'description' => 'Trainer Account',
                'is_active' => true,
            ],
            [
                'name' => 'Agent',
                'slug' => 'agent',
                'description' => 'Agent Account',
                'is_active' => true,
            ],
            [
                'name' => 'School Owner',
                'slug' => 'school_owner',
                'description' => 'School Owner Account',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}