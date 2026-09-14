<?php

namespace Tests\Feature;

use App\Jobs\AggregateDailyUsageJob;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAggregationJobTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected Plan $plan;
    protected User $user;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create(['name' => 'Acme Aggregation Corp']);

        $this->plan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Pro Aggregation Plan',
            'base_price' => 2000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 5000,
            'overage_rate' => 0.15,
        ]);

        $this->user = User::create([
            'name' => 'Bob Aggregator',
            'email' => 'bob@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_it_aggregates_usage_events_into_daily_aggregates(): void
    {
        // 3 events on target date: 100 + 250 + 150 = 500
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 100,
            'idempotency_key' => 'agg-key-1',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 250,
            'idempotency_key' => 'agg-key-2',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 150,
            'idempotency_key' => 'agg-key-3',
        ]);

        // Event on different date: should not be included
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-15',
            'units' => 999,
            'idempotency_key' => 'agg-key-diff-date',
        ]);

        // Execute aggregation job
        $job = new AggregateDailyUsageJob('2026-09-14');
        $job->handle();

        $this->assertDatabaseHas('daily_usage_aggregates', [
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'total_usage' => 500,
        ]);

        $aggregate = DailyUsageAggregate::where('subscription_id', $this->subscription->id)
            ->where('usage_date', '2026-09-14')
            ->first();

        $this->assertNotNull($aggregate);
        $this->assertEquals(500, $aggregate->total_usage);
    }

    public function test_it_is_idempotent_when_daily_aggregation_runs_multiple_times(): void
    {
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 300,
            'idempotency_key' => 'agg-idem-1',
        ]);

        $job = new AggregateDailyUsageJob('2026-09-14');
        
        // First run
        $job->handle();
        $this->assertEquals(1, DailyUsageAggregate::count());
        $this->assertEquals(300, DailyUsageAggregate::first()->total_usage);

        // Second run (re-aggregation should upsert, not duplicate or inflate total)
        $job->handle();
        $this->assertEquals(1, DailyUsageAggregate::count());
        $this->assertEquals(300, DailyUsageAggregate::first()->total_usage);
    }

    public function test_it_runs_artisan_command_usage_aggregate_daily(): void
    {
        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 420,
            'idempotency_key' => 'agg-cmd-1',
        ]);

        $this->artisan('usage:aggregate-daily', ['--date' => '2026-09-14'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('daily_usage_aggregates', [
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'total_usage' => 420,
        ]);
    }
}
