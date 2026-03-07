<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Admin',
                'email' => 'admin@asyncafrica.com',
                'phone' => '0782667888',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'admin',
            ],
            [
                'name' => 'ASYNC CEO',
                'email' => 'ceo@asyncafrica.com',
                'phone' => '0788325348',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'admin',
            ],
            
          
        ];

        foreach ($users as $item) {
            $roleSlug = $item['role_slug'];
            unset($item['role_slug']);

            $user = User::updateOrCreate(
                ['email' => $item['email']],
                $item
            );

            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $role = Role::where('slug', $roleSlug)->first();

                if ($role) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }
        }
    }
}