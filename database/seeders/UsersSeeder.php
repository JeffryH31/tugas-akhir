<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            ['name' => 'Sasya Rahma', 'email' => 'admin@example.com'],
            ['name' => 'Andi Fullstack', 'email' => 'andi@example.com'],
            ['name' => 'Dian Frontend', 'email' => 'dian@example.com'],
            ['name' => 'Budi Backend', 'email' => 'budi@example.com'],
            ['name' => 'Rina QA', 'email' => 'rina@example.com'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                ]
            );
        }
    }
}
