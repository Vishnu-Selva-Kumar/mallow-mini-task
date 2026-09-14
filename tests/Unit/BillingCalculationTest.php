<?php

namespace Tests\Unit;

use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected BillingService $billingService;
    protected Merchant $merchant;
    protected Plan $basicPlan;
    protected Plan $premiumPlan;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = new BillingService();

        $this->merchant = Merchant::create(['name' => 'Acme Test Corp']);

        $this->basicPlan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Basic Plan',
            'base_price' => 1000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.50,
        ]);

        $this->premiumPlan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Premium Plan',
            'base_price' => 3000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 5000,
            'overage_rate' => 0.20,
        ]);

        $this->user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function test_it_calculates_zero_overage_when_usage_within_allowance(): void
    {
        $cycleStart = Carbon::parse('2026-09-01');
        $cycleEnd = Carbon::parse('2026-09-30');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'created_at' => $cycleStart,
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => $cycleStart,
            'ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 500,
            'idempotency_key' => 'evt-test-1',
        ]);

        $result = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(1000.00, $result['base_amount']);
        $this->assertEquals(0.00, $result['overage_amount']);
        $this->assertEquals(1000.00, $result['total_amount']);
        $this->assertEquals(500, $result['units_used']);
    }

    public function test_it_calculates_overage_charges_correctly_when_usage_exceeds_allowance(): void
    {
        $cycleStart = Carbon::parse('2026-09-01');
        $cycleEnd = Carbon::parse('2026-09-30');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'created_at' => $cycleStart,
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => $cycleStart,
            'ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 800,
            'idempotency_key' => 'evt-test-2a',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-15',
            'units' => 700,
            'idempotency_key' => 'evt-test-2b',
        ]);

        $result = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(1000.00, $result['base_amount']);
        $this->assertEquals(250.00, $result['overage_amount']);
        $this->assertEquals(1250.00, $result['total_amount']);
        $this->assertEquals(1500, $result['units_used']);
    }

    public function test_it_calculates_prorated_base_price_for_mid_cycle_subscription_start(): void
    {
        $cycleStart = Carbon::parse('2026-09-01');
        $cycleEnd = Carbon::parse('2026-09-30');
        $startDate = Carbon::parse('2026-09-16');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'created_at' => $startDate,
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => $startDate,
            'ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-20',
            'units' => 600,
            'idempotency_key' => 'evt-test-3',
        ]);

        $result = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(500.00, $result['base_amount']);
        $this->assertEquals(50.00, $result['overage_amount']);
        $this->assertEquals(550.00, $result['total_amount']);
        $this->assertEquals(600, $result['units_used']);
    }

    public function test_it_handles_mid_cycle_plan_upgrade_with_segmented_rates_and_allowances(): void
    {
        $cycleStart = Carbon::parse('2026-09-01');
        $cycleEnd = Carbon::parse('2026-09-30');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->premiumPlan->id,
            'status' => 'active',
            'created_at' => $cycleStart,
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-15 23:59:59',
            'status' => 'closed',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->premiumPlan->id,
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-08',
            'units' => 700,
            'idempotency_key' => 'evt-p1',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-22',
            'units' => 3000,
            'idempotency_key' => 'evt-p2',
        ]);

        $result = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(2000.00, $result['base_amount']);
        $this->assertEquals(200.00, $result['overage_amount']);
        $this->assertEquals(2200.00, $result['total_amount']);
        $this->assertEquals(3700, $result['units_used']);
        $this->assertCount(2, $result['segments']);
    }
}
