<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['email' => 'admin@ictfoundation.org.np', 'name' => 'Super Admin', 'role' => Role::SuperAdmin],
            ['email' => 'eventadmin@ictfoundation.org.np', 'name' => 'Event Admin', 'role' => Role::EventAdmin],
            ['email' => 'registration@ictfoundation.org.np', 'name' => 'Registration Desk', 'role' => Role::RegistrationStaff],
            ['email' => 'scanner@ictfoundation.org.np', 'name' => 'Scanner Staff', 'role' => Role::ScannerStaff],
            ['email' => 'finance@ictfoundation.org.np', 'name' => 'Finance', 'role' => Role::Finance],
            ['email' => 'viewer@ictfoundation.org.np', 'name' => 'Viewer', 'role' => Role::Viewer],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role']->value,
                ],
            );
        }
    }
}
