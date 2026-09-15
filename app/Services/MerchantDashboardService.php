<?php

namespace App\Services;

use App\DTOs\DashboardMetricsDTO;
use App\Models\DailyUsageAggregate;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\UsageEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class MerchantDashboardService
{
    public function __construct(
        protected BillingService $billingService = new BillingService(),
        protected PlanCacheService $planCacheService = new PlanCacheService(),
    ) {}

    /**
     * Retrieve complete analytics dashboard payload for a given merchant.
     */
    public function getDashboardData(Merchant $merchant, ?Carbon $targetDate = null): DashboardMetricsDTO
    {
        $target = $targetDate ?? Carbon::now();
        $plans = $merchant->plans;
        $planIds = $plans->pluck('id')->all();

        // Active subscriptions for this merchant's plans
        $subscriptions = Subscription::whereIn('plan_id', $planIds)
            ->where('status', 'active')
            ->with(['user', 'plan', 'activePeriod'])
            ->get();

        // Determine current billing cycle start and end
        $firstActivePeriod = SubscriptionPeriod::whereIn('subscription_id', $subscriptions->pluck('id'))
            ->where('status', 'active')
            ->first();

        if ($firstActivePeriod) {
            $cycleStart = Carbon::parse($firstActivePeriod->starts_at)->startOfDay();
            $cycleEnd = Carbon::parse($firstActivePeriod->ends_at)->startOfDay();
        } else {
            $cycleStart = $target->copy()->startOfMonth();
            $cycleEnd = $target->copy()->endOfMonth();
        }

        $totalCycleUsage = 0;
        $totalPlanAllowance = 0;
        $totalProjectedOverageRevenue = 0.0;
        $totalProjectedOverageUnits = 0;
        $customerUsageList = [];
        $churnRiskList = [];

        // Previous month cycle for Month-over-Month (MoM) comparison
        $prevCycleStart = $cycleStart->copy()->subMonth();
        $prevCycleEnd = $cycleStart->copy()->subDay();

        foreach ($subscriptions as $sub) {
            $billing = $this->billingService->calculateSubscriptionBilling($sub, $cycleStart, $cycleEnd);
            $plan = $sub->plan;
            $allowance = (int) ($plan->included_units ?? 0);
            $unitsUsed = (int) $billing['units_used'];
            $overageAmount = (float) $billing['overage_amount'];
            $overageUnits = max(0, $unitsUsed - $allowance);

            $totalCycleUsage += $unitsUsed;
            $totalPlanAllowance += $allowance;
            $totalProjectedOverageRevenue += $overageAmount;
            $totalProjectedOverageUnits += $overageUnits;

            $allowancePercent = $allowance > 0 ? round(($unitsUsed / $allowance) * 100, 1) : 0.0;

            $customerUsageList[] = [
                'user_id' => $sub->user_id,
                'customer_name' => $sub->user->name ?? "User #{$sub->user_id}",
                'customer_email' => $sub->user->email ?? '',
                'plan_name' => $plan->name ?? 'Default',
                'usage_units' => $unitsUsed,
                'allowance_units' => $allowance,
                'percentage_of_allowance' => $allowancePercent,
            ];

            // MoM Churn Risk Calculation: Usage dropped > 50% vs previous cycle
            $prevUsage = (int) UsageEvent::where('subscription_id', $sub->id)
                ->whereBetween('usage_date', [$prevCycleStart->toDateString(), $prevCycleEnd->toDateString()])
                ->sum('units');

            if ($prevUsage > 0) {
                $drop = ($prevUsage - $unitsUsed) / $prevUsage;
                if ($drop > 0.50) {
                    $churnRiskList[] = [
                        'user_id' => $sub->user_id,
                        'customer_name' => $sub->user->name ?? "User #{$sub->user_id}",
                        'previous_cycle_usage' => $prevUsage,
                        'current_cycle_usage' => $unitsUsed,
                        'drop_percentage' => round($drop * 100, 1),
                    ];
                }
            }
        }

        // Sort top customers by usage descending and take top 5
        usort($customerUsageList, fn ($a, $b) => $b['usage_units'] <=> $a['usage_units']);
        $topCustomers = array_slice($customerUsageList, 0, 5);

        // Sort churn risks by drop percentage descending
        usort($churnRiskList, fn ($a, $b) => $b['drop_percentage'] <=> $a['drop_percentage']);

        // 30-Day Chronological Usage Trend
        $trendEndDate = $cycleEnd->copy();
        $trendStartDate = $trendEndDate->copy()->subDays(29);
        $subscriptionIds = $subscriptions->pluck('id')->all();

        $dailyTotals = [];
        if (!empty($subscriptionIds)) {
            // First check daily aggregates table
            $dailyTotals = DailyUsageAggregate::whereIn('subscription_id', $subscriptionIds)
                ->whereBetween('usage_date', [$trendStartDate->toDateString(), $trendEndDate->toDateString()])
                ->groupBy('usage_date')
                ->selectRaw('usage_date, SUM(total_usage) as total_units')
                ->pluck('total_units', 'usage_date')
                ->toArray();

            // Supplement with raw events if aggregates haven't run for given days
            if (empty($dailyTotals)) {
                $dailyTotals = UsageEvent::whereIn('subscription_id', $subscriptionIds)
                    ->whereBetween('usage_date', [$trendStartDate->toDateString(), $trendEndDate->toDateString()])
                    ->groupBy('usage_date')
                    ->selectRaw('usage_date, SUM(units) as total_units')
                    ->pluck('total_units', 'usage_date')
                    ->toArray();
            }
        }

        $dailyUsageTrend = [];
        $cursor = $trendStartDate->copy();
        while ($cursor->lte($trendEndDate)) {
            $dateStr = $cursor->toDateString();
            $dailyUsageTrend[] = [
                'date' => $cursor->format('M d'),
                'full_date' => $dateStr,
                'units' => (int) ($dailyTotals[$dateStr] ?? 0),
            ];
            $cursor->addDay();
        }

        // System Status Widget Data
        $redisStatus = 'operational';
        try {
            Redis::ping();
        } catch (\Throwable) {
            $redisStatus = 'offline';
        }

        $systemStatus = [
            'redis_cache' => [
                'status' => $redisStatus,
                'ttl_minutes' => 10,
                'driver' => config('cache.default', 'redis'),
            ],
            'nightly_aggregation' => [
                'status' => 'operational',
                'chunk_size' => 5000,
                'schedule' => 'Nightly (00:00 UTC)',
            ],
            'rate_limit' => [
                'status' => 'enforced',
                'limit' => 120,
                'interval' => '1 minute',
            ],
        ];

        // Active plan summary
        $activePlan = $plans->first();
        $activePlanData = $activePlan ? [
            'id' => $activePlan->id,
            'name' => $activePlan->name,
            'billing_cycle' => $activePlan->billing_cycle,
            'base_price' => (float) $activePlan->base_price,
            'included_units' => (int) $activePlan->included_units,
            'overage_rate' => (float) $activePlan->overage_rate,
        ] : null;

        $usagePercentage = $totalPlanAllowance > 0
            ? round(($totalCycleUsage / $totalPlanAllowance) * 100, 1)
            : 0.0;

        return new DashboardMetricsDTO(
            merchant: [
                'id' => $merchant->id,
                'name' => $merchant->name,
            ],
            activePlan: $activePlanData,
            metrics: [
                'current_cycle_usage' => $totalCycleUsage,
                'total_allowance' => $totalPlanAllowance,
                'usage_percentage' => $usagePercentage,
                'projected_overage_revenue' => round($totalProjectedOverageRevenue, 2),
                'projected_overage_units' => $totalProjectedOverageUnits,
                'active_subscribers' => $subscriptions->count(),
                'cycle_start' => $cycleStart->toDateString(),
                'cycle_end' => $cycleEnd->toDateString(),
            ],
            topCustomers: $topCustomers,
            churnRisks: $churnRiskList,
            dailyUsageTrend: $dailyUsageTrend,
            systemStatus: $systemStatus,
        );
    }
}
