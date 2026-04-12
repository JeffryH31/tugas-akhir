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
            ['name' => 'Sasya Rahma',    'email' => 'admin@example.com', 'hourly_rate' => 100000],
            ['name' => 'Andi Fullstack', 'email' => 'andi@example.com',  'hourly_rate' => 44000],
            ['name' => 'Dian Frontend',  'email' => 'dian@example.com',  'hourly_rate' => 50000],
            ['name' => 'Budi Backend',   'email' => 'budi@example.com',  'hourly_rate' => 50000],
            ['name' => 'Rina QA',        'email' => 'rina@example.com',  'hourly_rate' => 40000],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'        => $data['name'],
                    'password'    => $password,
                    'hourly_rate' => $data['hourly_rate'],
                ]
            );
        }
    }
}
