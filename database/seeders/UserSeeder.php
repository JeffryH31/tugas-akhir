<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create regular user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create default workspace
        $workspace = Workspace::create([
            'name' => 'My Workspace',
            'description' => 'Default workspace',
            'owner_id' => $admin->id,
        ]);

        // Add users to workspace
        $workspace->members()->attach($admin->id, ['role' => 'owner']);
        $workspace->members()->attach($user->id, ['role' => 'member']);
    }
}
