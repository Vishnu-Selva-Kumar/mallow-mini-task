<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('subscription:change-plan {subscription : Subscription ID} {plan : Target Plan ID} {--date= : Effective change date (YYYY-MM-DD, defaults to today)}')]
#[Description('Perform a mid-cycle plan upgrade or downgrade with segregated proration and overage calculation')]
class ChangeSubscriptionPlanCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        SubscriptionService $subscriptionService,
        BillingService $billingService
    ): int {
        $subscriptionId = (int) $this->argument('subscription');
        $planId = (int) $this->argument('plan');
        $dateInput = $this->option('date');

        $subscription = Subscription::with(['plan', 'user'])->find($subscriptionId);
        if (!$subscription) {
            $this->error("Subscription #{$subscriptionId} not found.");
            return self::FAILURE;
        }

        $newPlan = Plan::find($planId);
        if (!$newPlan) {
            $this->error("Plan #{$planId} not found.");
            return self::FAILURE;
        }

        $effectiveDate = $dateInput
            ? Carbon::parse($dateInput)->startOfDay()
            : Carbon::now()->startOfDay();

        $oldPlan = $subscription->plan;

        $this->info("Initiating mid-cycle plan change...");
        $this->line("• Customer: <comment>{$subscription->user->name} ({$subscription->user->email})</comment>");
        $this->line("• Current Plan: <comment>{$oldPlan->name}</comment> (Base: ₹{$oldPlan->base_price}, Included: {$oldPlan->included_units}, Overage: ₹{$oldPlan->overage_rate}/unit)");
        $this->line("• Target Plan: <comment>{$newPlan->name}</comment> (Base: ₹{$newPlan->base_price}, Included: {$newPlan->included_units}, Overage: ₹{$newPlan->overage_rate}/unit)");
        $this->line("• Effective Date: <comment>{$effectiveDate->toDateString()}</comment>");

        try {
            $result = $subscriptionService->changePlan($subscription, $newPlan, $effectiveDate);
        } catch (Throwable $e) {
            $this->error("Failed to change plan: {$e->getMessage()}");
            return self::FAILURE;
        }

        $changeTypeUpper = strtoupper($result['change_type']);
        $this->newLine();
        $this->info("✔ Plan successfully updated to {$newPlan->name} [{$changeTypeUpper}]");

        // Calculate and display segregated billing breakdown for the billing cycle
        $cycleStart = $effectiveDate->copy()->startOfMonth();
        $cycleEnd = $effectiveDate->copy()->endOfMonth();

        $billing = $billingService->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        $this->newLine();
        $this->info("Segregated Billing Breakdown ({$cycleStart->toDateString()} to {$cycleEnd->toDateString()}):");

        $rows = [];
        foreach ($billing['segments'] as $index => $segment) {
            $rows[] = [
                'Phase' => 'Phase ' . ($index + 1),
                'Plan' => $segment['plan_name'],
                'Days' => $segment['segment_days'] . ' days',
                'Prorated Base' => '₹ ' . number_format($segment['segment_base'], 2),
                'Allowance' => number_format($segment['allowance']) . ' units',
                'Recorded Usage' => number_format($segment['units_used']) . ' units',
                'Overage Units' => number_format($segment['overage_units']) . ' units',
                'Overage Fee' => '₹ ' . number_format($segment['overage_amount'], 2),
            ];
        }

        $this->table(
            ['Phase', 'Plan', 'Active Duration', 'Prorated Base', 'Prorated Allowance', 'Units Used', 'Overage Units', 'Overage Fee'],
            $rows
        );

        $this->newLine();
        $this->line("  <info>Total Prorated Base:</info>    ₹ " . number_format($billing['base_amount'], 2));
        $this->line("  <info>Total Overage Fees:</info>     ₹ " . number_format($billing['overage_amount'], 2));
        $this->line("  <comment>Total Projected Invoiced:</comment> ₹ " . number_format($billing['total_amount'], 2));
        $this->newLine();

        return self::SUCCESS;
    }
}
