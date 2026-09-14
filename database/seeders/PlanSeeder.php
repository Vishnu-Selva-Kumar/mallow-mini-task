<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Merchant 1 (Acme Corp) Plans
        Plan::firstOrCreate(
            ['id' => 1],
            [
                'merchant_id' => 1,
                'name' => 'Basic Plan',
                'base_price' => 999.00,
                'billing_cycle' => 'monthly',
                'included_units' => 50000,
                'overage_rate' => 0.0500,
            ]
        );

        Plan::firstOrCreate(
            ['id' => 2],
            [
                'merchant_id' => 1,
                'name' => 'Premium Plan',
                'base_price' => 4999.00,
                'billing_cycle' => 'monthly',
                'included_units' => 250000,
                'overage_rate' => 0.0300,
            ]
        );

        // Merchant 2 (Starlight Tech) Plans
        Plan::firstOrCreate(
            ['id' => 3],
            [
                'merchant_id' => 2,
                'name' => 'Basic Plan',
                'base_price' => 1299.00,
                'billing_cycle' => 'monthly',
                'included_units' => 60000,
                'overage_rate' => 0.0450,
            ]
        );

        Plan::firstOrCreate(
            ['id' => 4],
            [
                'merchant_id' => 2,
                'name' => 'Premium Plan',
                'base_price' => 5999.00,
                'billing_cycle' => 'monthly',
                'included_units' => 300000,
                'overage_rate' => 0.0250,
            ]
        );
    }
}
