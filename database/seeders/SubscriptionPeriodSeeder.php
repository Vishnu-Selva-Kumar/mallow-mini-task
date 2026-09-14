<?php

namespace Database\Seeders;

use App\Models\SubscriptionPeriod;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SubscriptionPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $midMonth = Carbon::now()->startOfMonth()->addDays(14)->endOfDay();
        $afterMidMonth = Carbon::now()->startOfMonth()->addDays(15)->startOfDay();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Subscription 1: Demonstrates mid-cycle upgrade (Plan 1 -> Plan 2)
        SubscriptionPeriod::firstOrCreate(
            ['id' => 1],
            [
                'subscription_id' => 1,
                'plan_id' => 1,
                'starts_at' => $startOfMonth,
                'ends_at' => $midMonth,
                'status' => 'closed',
            ]
        );

        SubscriptionPeriod::firstOrCreate(
            ['id' => 2],
            [
                'subscription_id' => 1,
                'plan_id' => 2,
                'starts_at' => $afterMidMonth,
                'ends_at' => $endOfMonth,
                'status' => 'active',
            ]
        );

        // Subscriptions 2 to 12: Standard active billing cycle
        $standardSubs = [
            ['id' => 3,  'subscription_id' => 2,  'plan_id' => 1],
            ['id' => 4,  'subscription_id' => 3,  'plan_id' => 4],
            ['id' => 5,  'subscription_id' => 4,  'plan_id' => 3],
            ['id' => 6,  'subscription_id' => 5,  'plan_id' => 2],
            ['id' => 7,  'subscription_id' => 6,  'plan_id' => 1],
            ['id' => 8,  'subscription_id' => 7,  'plan_id' => 4],
            ['id' => 9,  'subscription_id' => 8,  'plan_id' => 3],
            ['id' => 10, 'subscription_id' => 9,  'plan_id' => 2],
            ['id' => 11, 'subscription_id' => 10, 'plan_id' => 1],
            ['id' => 12, 'subscription_id' => 11, 'plan_id' => 4],
            ['id' => 13, 'subscription_id' => 12, 'plan_id' => 3],
        ];

        foreach ($standardSubs as $period) {
            SubscriptionPeriod::firstOrCreate(
                ['id' => $period['id']],
                [
                    'subscription_id' => $period['subscription_id'],
                    'plan_id' => $period['plan_id'],
                    'starts_at' => $startOfMonth,
                    'ends_at' => $endOfMonth,
                    'status' => 'active',
                ]
            );
        }
    }
}
