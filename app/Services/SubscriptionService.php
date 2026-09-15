<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    /**
     * Change a subscription's plan mid-cycle (Upgrade or Downgrade).
     *
     * @param Subscription $subscription
     * @param Plan $newPlan
     * @param Carbon|null $effectiveDate
     * @return array{
     *     subscription: Subscription,
     *     old_period: ?SubscriptionPeriod,
     *     new_period: SubscriptionPeriod,
     *     change_type: string,
     *     effective_date: Carbon
     * }
     *
     * @throws InvalidArgumentException
     */
    public function changePlan(
        Subscription $subscription,
        Plan $newPlan,
        ?Carbon $effectiveDate = null
    ): array {
        $effectiveDate = $effectiveDate ? $effectiveDate->copy() : Carbon::now();
        $currentPlan = $subscription->plan;

        if ($currentPlan && $currentPlan->merchant_id !== $newPlan->merchant_id) {
            throw new InvalidArgumentException("Cannot switch to a plan belonging to a different merchant.");
        }

        if ($subscription->plan_id === $newPlan->id) {
            throw new InvalidArgumentException("Subscription is already on plan: {$newPlan->name}.");
        }

        // Determine upgrade vs. downgrade
        $changeType = 'upgrade';
        if ($currentPlan) {
            $isHigherPrice = (float) $newPlan->base_price > (float) $currentPlan->base_price;
            $isSamePriceHigherUnits = (float) $newPlan->base_price === (float) $currentPlan->base_price
                && (int) $newPlan->included_units > (int) $currentPlan->included_units;

            $changeType = ($isHigherPrice || $isSamePriceHigherUnits) ? 'upgrade' : 'downgrade';
        }

        return DB::transaction(function () use ($subscription, $currentPlan, $newPlan, $effectiveDate, $changeType) {
            // Find current active period
            $activePeriod = $subscription->periods()
                ->where('status', 'active')
                ->orderByDesc('starts_at')
                ->first();

            $originalCycleEnd = $activePeriod && $activePeriod->ends_at
                ? $activePeriod->ends_at->copy()
                : $effectiveDate->copy()->endOfMonth();

            // Close active period just before effective date
            $previousPeriodEnd = $effectiveDate->copy()->startOfDay()->subSecond();

            if ($activePeriod) {
                // If effective date is at or before active period start, adjust gracefully
                if ($previousPeriodEnd->lessThan($activePeriod->starts_at)) {
                    $previousPeriodEnd = $activePeriod->starts_at->copy();
                }

                $activePeriod->update([
                    'ends_at' => $previousPeriodEnd,
                    'status' => 'closed',
                ]);
            }

            // Create new active period from effective date to cycle end
            $newPeriod = SubscriptionPeriod::create([
                'subscription_id' => $subscription->id,
                'plan_id' => $newPlan->id,
                'starts_at' => $effectiveDate->copy()->startOfDay(),
                'ends_at' => $originalCycleEnd,
                'status' => 'active',
            ]);

            // Update main subscription model
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);

            $subscription->refresh();

            return [
                'subscription' => $subscription,
                'old_period' => $activePeriod,
                'new_period' => $newPeriod,
                'change_type' => $changeType,
                'effective_date' => $effectiveDate,
            ];
        });
    }
}
