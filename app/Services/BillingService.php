<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BillingService
{
    public function __construct(
        protected PlanCacheService $planCacheService = new PlanCacheService()
    ) {}

    /**
     * Calculate billing breakdown for a subscription across a given date range.
     *
     * @return array{
     *     base_amount: float,
     *     overage_amount: float,
     *     total_amount: float,
     *     units_used: int,
     *     segments: array<int, mixed>
     * }
     */
    public function calculateSubscriptionBilling(
        Subscription $subscription,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): array {
        $normalizedCycleStart = $cycleStart->copy()->startOfDay();
        $normalizedCycleEnd = $cycleEnd->copy()->startOfDay();
        $totalCycleDays = max(1, $normalizedCycleStart->diffInDays($normalizedCycleEnd) + 1);

        // Fetch periods overlapping the cycle
        $periods = SubscriptionPeriod::where('subscription_id', $subscription->id)
            ->where('starts_at', '<=', $cycleEnd->copy()->endOfDay())
            ->where('ends_at', '>=', $cycleStart->copy()->startOfDay())
            ->orderBy('starts_at')
            ->get();

        // If no periods exist, fallback to single default segment using current subscription plan
        if ($periods->isEmpty()) {
            return $this->calculateSinglePeriodBilling($subscription, $subscription->plan, $cycleStart, $cycleEnd, $totalCycleDays);
        }

        $totalBaseAmount = 0.0;
        $totalOverageAmount = 0.0;
        $totalUnitsUsed = 0;
        $segments = [];

        foreach ($periods as $period) {
            $periodStart = Carbon::parse($period->starts_at)->startOfDay();
            $periodEnd = Carbon::parse($period->ends_at)->startOfDay();

            $segmentStart = $periodStart->max($normalizedCycleStart);
            $segmentEnd = $periodEnd->min($normalizedCycleEnd);
            $segmentDays = max(0, $segmentStart->diffInDays($segmentEnd) + 1);

            $plan = $this->planCacheService->getPlan($period->plan_id) ?? Plan::find($period->plan_id);

            // Prorated base price for segment days
            $prorationFraction = $segmentDays / $totalCycleDays;
            $segmentBase = round((float) $plan->base_price * $prorationFraction, 2);

            // Fetch usage recorded in this segment
            $segmentUnits = (int) UsageEvent::where('subscription_id', $subscription->id)
                ->whereBetween('usage_date', [$segmentStart->toDateString(), $segmentEnd->toDateString()])
                ->sum('units');

            if ($segmentUnits === 0) {
                $segmentUnits = (int) \App\Models\DailyUsageAggregate::where('subscription_id', $subscription->id)
                    ->whereBetween('usage_date', [$segmentStart->toDateString(), $segmentEnd->toDateString()])
                    ->sum('total_usage');
            }

            // Prorate allowance to this segment duration
            $segmentAllowance = (int) round($plan->included_units * $prorationFraction);
            $overageUnits = max(0, $segmentUnits - $segmentAllowance);
            $segmentOverage = round($overageUnits * (float) $plan->overage_rate, 2);

            $totalBaseAmount += $segmentBase;
            $totalOverageAmount += $segmentOverage;
            $totalUnitsUsed += $segmentUnits;

            $segments[] = [
                'period_id' => $period->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'starts_at' => $segmentStart->toDateString(),
                'ends_at' => $segmentEnd->toDateString(),
                'segment_days' => $segmentDays,
                'segment_base' => $segmentBase,
                'units_used' => $segmentUnits,
                'allowance' => $segmentAllowance,
                'overage_units' => $overageUnits,
                'overage_amount' => $segmentOverage,
            ];
        }

        $totalAmount = round($totalBaseAmount + $totalOverageAmount, 2);

        return [
            'base_amount' => round($totalBaseAmount, 2),
            'overage_amount' => round($totalOverageAmount, 2),
            'total_amount' => $totalAmount,
            'units_used' => $totalUnitsUsed,
            'segments' => $segments,
        ];
    }

    /**
     * Generate and persist an invoice record for a subscription.
     */
    public function generateInvoice(
        Subscription $subscription,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): Invoice {
        $billing = $this->calculateSubscriptionBilling($subscription, $cycleStart, $cycleEnd);

        return Invoice::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'invoice_date' => $cycleEnd->toDateString(),
            'base_amount' => $billing['base_amount'],
            'overage_amount' => $billing['overage_amount'],
            'total_amount' => $billing['total_amount'],
            'units_used' => $billing['units_used'],
            'status' => 'pending',
        ]);
    }

    /**
     * Single segment calculation fallback.
     */
    protected function calculateSinglePeriodBilling(
        Subscription $subscription,
        Plan $plan,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        int $totalCycleDays
    ): array {
        // Check active days based on subscription created_at if created mid-cycle
        $normalizedCycleStart = $cycleStart->copy()->startOfDay();
        $normalizedCycleEnd = $cycleEnd->copy()->startOfDay();
        $activeStart = Carbon::parse($subscription->created_at)->startOfDay()->max($normalizedCycleStart);
        $activeDays = max(1, $activeStart->diffInDays($normalizedCycleEnd) + 1);
        $prorationFraction = $activeDays / $totalCycleDays;

        $baseAmount = round((float) $plan->base_price * $prorationFraction, 2);

        $totalUnits = (int) UsageEvent::where('subscription_id', $subscription->id)
            ->whereBetween('usage_date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
            ->sum('units');

        if ($totalUnits === 0) {
            $totalUnits = (int) \App\Models\DailyUsageAggregate::where('subscription_id', $subscription->id)
                ->whereBetween('usage_date', [$cycleStart->toDateString(), $cycleEnd->toDateString()])
                ->sum('total_usage');
        }

        $allowance = (int) round($plan->included_units * $prorationFraction);
        $overageUnits = max(0, $totalUnits - $allowance);
        $overageAmount = round($overageUnits * (float) $plan->overage_rate, 2);
        $totalAmount = round($baseAmount + $overageAmount, 2);

        return [
            'base_amount' => $baseAmount,
            'overage_amount' => $overageAmount,
            'total_amount' => $totalAmount,
            'units_used' => $totalUnits,
            'segments' => [
                [
                    'plan_name' => $plan->name,
                    'segment_days' => $activeDays,
                    'segment_base' => $baseAmount,
                    'units_used' => $totalUnits,
                    'allowance' => $allowance,
                    'overage_units' => $overageUnits,
                    'overage_amount' => $overageAmount,
                ],
            ],
        ];
    }
}
