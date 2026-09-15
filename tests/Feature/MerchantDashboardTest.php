<?php

namespace Tests\Feature;

use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create(['name' => 'Acme Corporation']);

        $this->plan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Growth Plan',
            'base_price' => 500.00,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.50,
        ]);
    }

    public function test_it_renders_html_dashboard_view_for_merchant(): void
    {
        $response = $this->get("/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.merchant');
        $response->assertSeeText('Acme Corporation');
        $response->assertSeeText('Growth Plan');
        $response->assertSeeText('30-Day Daily Usage Trend');
        $response->assertSeeText('Top 5 Customers by Usage');
        $response->assertSeeText('Churn Risk Alerts');
        $response->assertSeeText('Plan Pricing Cache');
    }

    public function test_it_returns_structured_json_dashboard_data(): void
    {
        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'merchant' => ['id', 'name'],
                'active_plan' => ['id', 'name', 'billing_cycle', 'base_price', 'included_units', 'overage_rate'],
                'metrics' => [
                    'current_cycle_usage',
                    'total_allowance',
                    'usage_percentage',
                    'projected_overage_revenue',
                    'projected_overage_units',
                    'active_subscribers',
                    'cycle_start',
                    'cycle_end',
                ],
                'top_customers',
                'churn_risks',
                'daily_usage_trend',
                'system_status' => ['redis_cache', 'nightly_aggregation', 'rate_limit'],
            ],
        ]);

        $this->assertEquals('Acme Corporation', $response->json('data.merchant.name'));
        $this->assertEquals('Growth Plan', $response->json('data.active_plan.name'));
    }

    public function test_it_correctly_calculates_current_cycle_usage_and_allowance(): void
    {
        $user1 = User::create(['name' => 'User One', 'email' => 'user1@example.com', 'password' => bcrypt('password')]);
        $user2 = User::create(['name' => 'User Two', 'email' => 'user2@example.com', 'password' => bcrypt('password')]);

        $sub1 = Subscription::create(['user_id' => $user1->id, 'plan_id' => $this->plan->id, 'status' => 'active']);
        $sub2 = Subscription::create(['user_id' => $user2->id, 'plan_id' => $this->plan->id, 'status' => 'active']);

        SubscriptionPeriod::create([
            'subscription_id' => $sub1->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $sub2->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $user1->id,
            'subscription_id' => $sub1->id,
            'usage_date' => '2026-09-10',
            'units' => 400,
            'idempotency_key' => 'evt-1',
        ]);

        UsageEvent::create([
            'user_id' => $user2->id,
            'subscription_id' => $sub2->id,
            'usage_date' => '2026-09-12',
            'units' => 600,
            'idempotency_key' => 'evt-2',
        ]);

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        // Total usage = 400 + 600 = 1000
        $this->assertEquals(1000, $response->json('data.metrics.current_cycle_usage'));
        // Total allowance = 1000 + 1000 = 2000
        $this->assertEquals(2000, $response->json('data.metrics.total_allowance'));
        // Usage % = (1000 / 2000) * 100 = 50.0%
        $this->assertEquals(50.0, $response->json('data.metrics.usage_percentage'));
        $this->assertEquals(2, $response->json('data.metrics.active_subscribers'));
    }

    public function test_it_accurately_projects_overage_revenue_for_customers_exceeding_allowance(): void
    {
        $user = User::create(['name' => 'High Usage User', 'email' => 'high@example.com', 'password' => bcrypt('password')]);
        $sub = Subscription::create(['user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'active']);

        SubscriptionPeriod::create([
            'subscription_id' => $sub->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        // Allowance is 1000 units. Record 1500 units => 500 units overage * 0.50 rate = 250.00 projected revenue
        UsageEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'usage_date' => '2026-09-14',
            'units' => 1500,
            'idempotency_key' => 'evt-overage-1',
        ]);

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $this->assertEquals(1500, $response->json('data.metrics.current_cycle_usage'));
        $this->assertEquals(500, $response->json('data.metrics.projected_overage_units'));
        $this->assertEquals(250.00, $response->json('data.metrics.projected_overage_revenue'));
    }

    public function test_it_returns_top_5_customers_ordered_by_usage_descending(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $user = User::create([
                'name' => "Customer {$i}",
                'email' => "cust{$i}@example.com",
                'password' => bcrypt('secret'),
            ]);

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $this->plan->id,
                'status' => 'active',
            ]);

            SubscriptionPeriod::create([
                'subscription_id' => $sub->id,
                'plan_id' => $this->plan->id,
                'starts_at' => '2026-09-01 00:00:00',
                'ends_at' => '2026-09-30 23:59:59',
                'status' => 'active',
            ]);

            UsageEvent::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'usage_date' => '2026-09-15',
                'units' => $i * 100, // 100, 200, 300, 400, 500, 600, 700
                'idempotency_key' => "top-test-{$i}",
            ]);
        }

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $topCustomers = $response->json('data.top_customers');

        // Should return at most 5 customers
        $this->assertCount(5, $topCustomers);

        // Highest usage customer should be first (Customer 7 with 700 units)
        $this->assertEquals('Customer 7', $topCustomers[0]['customer_name']);
        $this->assertEquals(700, $topCustomers[0]['usage_units']);

        // Second highest should be Customer 6 with 600 units
        $this->assertEquals('Customer 6', $topCustomers[1]['customer_name']);
        $this->assertEquals(600, $topCustomers[1]['usage_units']);

        // Fifth highest should be Customer 3 with 300 units
        $this->assertEquals('Customer 3', $topCustomers[4]['customer_name']);
        $this->assertEquals(300, $topCustomers[4]['usage_units']);
    }

    public function test_it_accurately_flags_churn_risk_when_usage_drops_over_50_percent_mom(): void
    {
        $user = User::create([
            'name' => 'At Risk Customer',
            'email' => 'atrisk@example.com',
            'password' => bcrypt('password'),
        ]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $sub->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        // Previous cycle (August 2026): 10,000 units
        UsageEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'usage_date' => '2026-08-15',
            'units' => 10000,
            'idempotency_key' => 'aug-usage',
        ]);

        // Current cycle (September 2026): 3,000 units (70% drop)
        UsageEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'usage_date' => '2026-09-10',
            'units' => 3000,
            'idempotency_key' => 'sep-usage',
        ]);

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $churnRisks = $response->json('data.churn_risks');

        $this->assertCount(1, $churnRisks);
        $this->assertEquals('At Risk Customer', $churnRisks[0]['customer_name']);
        $this->assertEquals(10000, $churnRisks[0]['previous_cycle_usage']);
        $this->assertEquals(3000, $churnRisks[0]['current_cycle_usage']);
        $this->assertEquals(70.0, $churnRisks[0]['drop_percentage']);
    }

    public function test_it_does_not_flag_customer_as_churn_risk_when_drop_is_under_50_percent(): void
    {
        $user = User::create([
            'name' => 'Healthy Customer',
            'email' => 'healthy@example.com',
            'password' => bcrypt('password'),
        ]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $sub->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        // Previous cycle: 10,000 units
        UsageEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'usage_date' => '2026-08-15',
            'units' => 10000,
            'idempotency_key' => 'aug-healthy',
        ]);

        // Current cycle: 7,000 units (30% drop, not exceeding 50%)
        UsageEvent::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'usage_date' => '2026-09-10',
            'units' => 7000,
            'idempotency_key' => 'sep-healthy',
        ]);

        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $churnRisks = $response->json('data.churn_risks');

        $this->assertEmpty($churnRisks);
    }

    public function test_it_returns_30_consecutive_days_of_usage_trend(): void
    {
        $response = $this->getJson("/api/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200);
        $trend = $response->json('data.daily_usage_trend');

        $this->assertCount(30, $trend);
        $this->assertArrayHasKey('date', $trend[0]);
        $this->assertArrayHasKey('full_date', $trend[0]);
        $this->assertArrayHasKey('units', $trend[0]);
    }

    public function test_it_returns_404_for_non_existent_merchant(): void
    {
        $response = $this->getJson("/api/merchants/999999/dashboard");

        $response->assertStatus(404);
    }
}
