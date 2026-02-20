<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@hecoapp.com'],
            [
                'full_name' => 'HCT Admin',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'hct_admin',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'collaborator@hecoapp.com'],
            [
                'full_name' => 'HCT Collaborator',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'hct_collaborator',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'traveller@hecoapp.com'],
            [
                'full_name' => 'Test Traveller',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'traveller',
                'status' => 'active',
            ]
        );
    }
}
