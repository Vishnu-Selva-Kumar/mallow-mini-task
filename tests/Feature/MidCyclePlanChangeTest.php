<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\BillingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MidCyclePlanChangeTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected Plan $basicPlan;
    protected Plan $premiumPlan;
    protected User $user;
    protected SubscriptionService $subscriptionService;
    protected BillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = new SubscriptionService();
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
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_subscription_service_handles_mid_cycle_upgrade_with_accurate_proration(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'created_at' => $cycleStart,
        ]);

        $initialPeriod = SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->basicPlan->id,
            'starts_at' => $cycleStart,
            'ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        $effectiveDate = Carbon::parse('2026-09-16 00:00:00');

        $result = $this->subscriptionService->changePlan($subscription, $this->premiumPlan, $effectiveDate);

        $this->assertEquals('upgrade', $result['change_type']);
        $this->assertEquals($this->premiumPlan->id, $subscription->fresh()->plan_id);

        // Check closed period
        $initialPeriod->refresh();
        $this->assertEquals('closed', $initialPeriod->status);
        $this->assertEquals('2026-09-15 23:59:59', $initialPeriod->ends_at->toDateTimeString());

        // Check newly activated period
        $newPeriod = $result['new_period'];
        $this->assertEquals('active', $newPeriod->status);
        $this->assertEquals($this->premiumPlan->id, $newPeriod->plan_id);
        $this->assertEquals('2026-09-16 00:00:00', $newPeriod->starts_at->toDateTimeString());
        $this->assertEquals('2026-09-30 23:59:59', $newPeriod->ends_at->toDateTimeString());
    }

    public function test_billing_calculates_segregated_prorations_and_overages_before_and_after_change(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

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

        // Usage in Period 1 (Basic Plan): 700 units on Sep 08
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-08',
            'units' => 700,
            'idempotency_key' => 'evt-mid-1',
        ]);

        // Execute upgrade on Sep 16
        $effectiveDate = Carbon::parse('2026-09-16 00:00:00');
        $this->subscriptionService->changePlan($subscription, $this->premiumPlan, $effectiveDate);

        // Usage in Period 2 (Premium Plan): 3,000 units on Sep 22
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-22',
            'units' => 3000,
            'idempotency_key' => 'evt-mid-2',
        ]);

        $billing = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        // Period 1 (15 days):
        // Base: 1000 * 15/30 = 500.00
        // Allowance: 1000 * 15/30 = 500 units
        // Usage: 700 units -> Overage: 200 units * 0.50 = 100.00
        //
        // Period 2 (15 days):
        // Base: 3000 * 15/30 = 1500.00
        // Allowance: 5000 * 15/30 = 2500 units
        // Usage: 3000 units -> Overage: 500 units * 0.20 = 100.00
        //
        // Totals:
        // Base: 500 + 1500 = 2000.00
        // Overage: 100 + 100 = 200.00
        // Total Invoiced: 2200.00
        // Units Used: 3700

        $this->assertEquals(2000.00, $billing['base_amount']);
        $this->assertEquals(200.00, $billing['overage_amount']);
        $this->assertEquals(2200.00, $billing['total_amount']);
        $this->assertEquals(3700, $billing['units_used']);
        $this->assertCount(2, $billing['segments']);

        // Check segment 1 details
        $this->assertEquals('Basic Plan', $billing['segments'][0]['plan_name']);
        $this->assertEquals(15, $billing['segments'][0]['segment_days']);
        $this->assertEquals(500.00, $billing['segments'][0]['segment_base']);
        $this->assertEquals(500, $billing['segments'][0]['allowance']);
        $this->assertEquals(700, $billing['segments'][0]['units_used']);
        $this->assertEquals(200, $billing['segments'][0]['overage_units']);
        $this->assertEquals(100.00, $billing['segments'][0]['overage_amount']);

        // Check segment 2 details
        $this->assertEquals('Premium Plan', $billing['segments'][1]['plan_name']);
        $this->assertEquals(15, $billing['segments'][1]['segment_days']);
        $this->assertEquals(1500.00, $billing['segments'][1]['segment_base']);
        $this->assertEquals(2500, $billing['segments'][1]['allowance']);
        $this->assertEquals(3000, $billing['segments'][1]['units_used']);
        $this->assertEquals(500, $billing['segments'][1]['overage_units']);
        $this->assertEquals(100.00, $billing['segments'][1]['overage_amount']);
    }

    public function test_mid_cycle_downgrade_correctly_segments_and_charges(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->premiumPlan->id,
            'status' => 'active',
            'created_at' => $cycleStart,
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->premiumPlan->id,
            'starts_at' => $cycleStart,
            'ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        // Downgrade on Sep 11 (10 days on Premium, 20 days on Basic)
        $effectiveDate = Carbon::parse('2026-09-11 00:00:00');
        $result = $this->subscriptionService->changePlan($subscription, $this->basicPlan, $effectiveDate);

        $this->assertEquals('downgrade', $result['change_type']);
        $this->assertEquals($this->basicPlan->id, $subscription->fresh()->plan_id);

        $billing = $this->billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        // Premium: 10 days / 30 = 1000.00 base
        // Basic: 20 days / 30 = 666.67 base
        $this->assertEquals(10, $billing['segments'][0]['segment_days']);
        $this->assertEquals(20, $billing['segments'][1]['segment_days']);
        $this->assertEquals(1000.00, $billing['segments'][0]['segment_base']);
        $this->assertEquals(666.67, $billing['segments'][1]['segment_base']);
        $this->assertEquals(1666.67, $billing['base_amount']);
    }

    public function test_cli_command_subscription_change_plan_executes_successfully(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

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

        $this->artisan('subscription:change-plan', [
            'subscription' => $subscription->id,
            'plan' => $this->premiumPlan->id,
            '--date' => '2026-09-16',
        ])
            ->expectsOutputToContain("Plan successfully updated to Premium Plan [UPGRADE]")
            ->expectsOutputToContain("Total Prorated Base")
            ->assertSuccessful();

        $this->assertEquals($this->premiumPlan->id, $subscription->fresh()->plan_id);
    }

    public function test_invoice_generation_persists_segmented_billing(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

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

        $effectiveDate = Carbon::parse('2026-09-16 00:00:00');
        $this->subscriptionService->changePlan($subscription, $this->premiumPlan, $effectiveDate);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-08',
            'units' => 700,
            'idempotency_key' => 'evt-inv-1',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-22',
            'units' => 3000,
            'idempotency_key' => 'evt-inv-2',
        ]);

        $invoice = $this->billingService->generateInvoice($subscription, $cycleStart, $cycleEnd);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'base_amount' => 2000.00,
            'overage_amount' => 200.00,
            'total_amount' => 2200.00,
            'units_used' => 3700,
        ]);
    }

    public function test_merchant_dashboard_reflects_mid_cycle_plan_changes(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

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

        // Upgrade mid-cycle on Sep 16
        $this->subscriptionService->changePlan($subscription, $this->premiumPlan, Carbon::parse('2026-09-16'));

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-08',
            'units' => 700,
            'idempotency_key' => 'evt-dash-1',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-22',
            'units' => 3000,
            'idempotency_key' => 'evt-dash-2',
        ]);

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'merchant' => [
                    'id' => $this->merchant->id,
                    'name' => 'Acme Test Corp',
                ],
                'metrics' => [
                    'current_cycle_usage' => 3700,
                    'total_allowance' => 3000, // 500 (Phase 1) + 2500 (Phase 2)
                    'projected_overage_revenue' => 200,
                    'projected_overage_units' => 700,
                ],
            ],
        ]);
    }

    public function test_cannot_change_to_a_plan_belonging_to_another_merchant(): void
    {
        $otherMerchant = Merchant::create(['name' => 'Other Corp']);
        $otherPlan = Plan::create([
            'merchant_id' => $otherMerchant->id,
            'name' => 'Other Plan',
            'base_price' => 5000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 10000,
            'overage_rate' => 0.10,
        ]);

        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot switch to a plan belonging to a different merchant.");

        $this->subscriptionService->changePlan($subscription, $otherPlan);
    }

    public function test_cannot_change_to_the_same_plan(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Subscription is already on plan: Basic Plan.");

        $this->subscriptionService->changePlan($subscription, $this->basicPlan);
    }
}
