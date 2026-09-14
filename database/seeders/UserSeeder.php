<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['id' => 1, 'name' => 'Beta Retail Pvt Ltd', 'email' => 'beta@example.com'],
            ['id' => 2, 'name' => 'Craft Foods Co.', 'email' => 'craft@example.com'],
            ['id' => 3, 'name' => 'Delta Mart', 'email' => 'delta@example.com'],
            ['id' => 4, 'name' => 'Nova Traders', 'email' => 'nova@example.com'],
            ['id' => 5, 'name' => 'QuickMart', 'email' => 'quickmart@example.com'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
