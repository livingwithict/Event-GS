<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['email' => 'admin@ictfoundation.org.np', 'name' => 'Super Admin', 'role' => 'super_admin'],
            ['email' => 'manager@ictfoundation.org.np', 'name' => 'Event Manager', 'role' => 'event_manager'],
            ['email' => 'scanner@ictfoundation.org.np', 'name' => 'Scanner User', 'role' => 'scanner'],
            ['email' => 'viewer@ictfoundation.org.np', 'name' => 'Viewer', 'role' => 'viewer'],
            ['email' => 'regstaff@ictfoundation.org.np', 'name' => 'Registration Staff', 'role' => 'registration_staff'],
            ['email' => 'finance@ictfoundation.org.np', 'name' => 'Finance User', 'role' => 'finance'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                ]
            );
        }
    }
}
