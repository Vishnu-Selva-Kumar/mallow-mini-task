<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
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

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected Plan $plan;
    protected User $user;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create(['name' => 'Invoice Test Merchant']);

        $this->plan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Gold Tier',
            'base_price' => 2000.00,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.50,
        ]);

        $this->user = User::create([
            'name' => 'Alice Invoice',
            'email' => 'alice@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'created_at' => '2026-09-01 00:00:00',
        ]);

        SubscriptionPeriod::create([
            'subscription_id' => $this->subscription->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'status' => 'active',
        ]);

        UsageEvent::create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-10',
            'units' => 1400,
            'idempotency_key' => 'invoice-usage-1',
        ]);
    }

    public function test_it_generates_invoice_for_active_subscription_at_cycle_end(): void
    {
        $job = new GenerateInvoiceJob(
            $this->subscription->id,
            '2026-09-01',
            '2026-09-30'
        );

        $job->handle(new BillingService());

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->user->id,
            'base_amount' => 2000.00,
            'overage_amount' => 200.00, // (1400 - 1000) * 0.50 = 200
            'total_amount' => 2200.00,
            'units_used' => 1400,
            'status' => 'pending',
        ]);
    }

    public function test_it_runs_artisan_billing_generate_invoices_command(): void
    {
        $this->artisan('billing:generate-invoices', [
            '--start' => '2026-09-01',
            '--end' => '2026-09-30',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->user->id,
            'total_amount' => 2200.00,
        ]);
    }
}
