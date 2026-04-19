<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
                'email' => 'shyakas83@gmail.com',
                'phone' => '0782667888',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'admin',
            ],
            [
                'name' => 'ASYNC CEO',
                'email' => 'nsanzumuhirecyprien0@gmail.com',
                'phone' => '0788325348',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'ceo',
            ],
            [
                'name' => 'System Trainer',
                'email' => 'trainer@asyncafrica.com',
                'phone' => '0780000001',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'trainer',
            ],
            [
                'name' => 'Student User',
                'email' => 'student@asyncafrica.com',
                'phone' => '0780000002',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'student',
            ],
            [
                'name' => 'System Agent',
                'email' => 'agent@asyncafrica.com',
                'phone' => '0780000003',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'agent',
            ],
            [
                'name' => 'School Owner User',
                'email' => 'schoolowner@asyncafrica.com',
                'phone' => '0780000004',
                'password' => 'password123',
                'status' => 'active',
                'is_active' => true,
                'role_slug' => 'school_owner',
            ],
        ];

        foreach ($users as $item) {
            $roleSlug = $item['role_slug'];
            unset($item['role_slug']);

            $plainPassword = $item['password'];
            $item['password'] = Hash::make($plainPassword);

            $user = User::updateOrCreate(
                ['email' => $item['email']],
                $item
            );

            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $role = Role::where('slug', $roleSlug)->first();

                if ($role) {
                    $user->roles()->sync([$role->id]);
                }
            }
        }
    }
}