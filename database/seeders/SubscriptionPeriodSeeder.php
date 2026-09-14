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

        // Subscriptions 2 to 5: Standard active cycle
        $standardSubs = [
            ['id' => 3, 'subscription_id' => 2, 'plan_id' => 2],
            ['id' => 4, 'subscription_id' => 3, 'plan_id' => 2],
            ['id' => 5, 'subscription_id' => 4, 'plan_id' => 1],
            ['id' => 6, 'subscription_id' => 5, 'plan_id' => 1],
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
