<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subscriptions = [
            ['id' => 1, 'user_id' => 1, 'plan_id' => 2, 'status' => 'active'],
            ['id' => 2, 'user_id' => 2, 'plan_id' => 1, 'status' => 'active'],
            ['id' => 3, 'user_id' => 3, 'plan_id' => 4, 'status' => 'active'],
            ['id' => 4, 'user_id' => 4, 'plan_id' => 3, 'status' => 'active'],
            ['id' => 5, 'user_id' => 5, 'plan_id' => 2, 'status' => 'active'],
            ['id' => 6, 'user_id' => 6, 'plan_id' => 1, 'status' => 'active'],
            ['id' => 7, 'user_id' => 7, 'plan_id' => 4, 'status' => 'active'],
            ['id' => 8, 'user_id' => 8, 'plan_id' => 3, 'status' => 'active'],
            ['id' => 9, 'user_id' => 9, 'plan_id' => 2, 'status' => 'active'],
            ['id' => 10, 'user_id' => 10, 'plan_id' => 1, 'status' => 'active'],
            ['id' => 11, 'user_id' => 11, 'plan_id' => 4, 'status' => 'active'],
            ['id' => 12, 'user_id' => 12, 'plan_id' => 3, 'status' => 'active'],
        ];

        foreach ($subscriptions as $sub) {
            Subscription::firstOrCreate(
                ['id' => $sub['id']],
                [
                    'user_id' => $sub['user_id'],
                    'plan_id' => $sub['plan_id'],
                    'status' => $sub['status'],
                ]
            );
        }
    }
}
