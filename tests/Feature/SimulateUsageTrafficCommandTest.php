<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulateUsageTrafficCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_successfully_simulates_usage_traffic_for_active_users(): void
    {
        $merchant = Merchant::create(['name' => 'Traffic Merchant']);

        $plan = Plan::create([
            'merchant_id' => $merchant->id,
            'name' => 'Traffic Plan',
            'base_price' => 1000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 10000,
            'overage_rate' => 0.05,
        ]);

        $user1 = User::create([
            'name' => 'User One',
            'email' => 'u1@example.com',
            'password' => bcrypt('secret'),
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'u2@example.com',
            'password' => bcrypt('secret'),
        ]);

        Subscription::create([
            'user_id' => $user1->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        Subscription::create([
            'user_id' => $user2->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->artisan('usage:simulate-traffic', [
            '--users' => 2,
            '--records-per-user' => 25,
            '--start-date' => '2026-09-01',
            '--end-date' => '2026-09-30',
        ])->assertExitCode(0);

        $this->assertEquals(50, UsageEvent::count());
        $this->assertEquals(25, UsageEvent::where('user_id', $user1->id)->count());
        $this->assertEquals(25, UsageEvent::where('user_id', $user2->id)->count());
    }

    public function test_it_returns_failure_when_no_active_users_exist(): void
    {
        $this->artisan('usage:simulate-traffic', [
            '--users' => 5,
            '--records-per-user' => 10,
        ])->assertExitCode(1);

        $this->assertEquals(0, UsageEvent::count());
    }
}
