<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PlanCacheService
{
    public const TTL_SECONDS = 600; // 10 minutes TTL per wireframe specification

    /**
     * Retrieve a plan from cache or database.
     */
    public function getPlan(int $planId): ?Plan
    {
        $cacheKey = $this->getPlanCacheKey($planId);

        $plan = Cache::get($cacheKey);

        if ($plan instanceof Plan) {
            return $plan;
        }

        // Evict corrupted or incomplete cached object if present
        if ($plan !== null) {
            Cache::forget($cacheKey);
        }

        $plan = Plan::find($planId);

        if ($plan) {
            Cache::put($cacheKey, $plan, self::TTL_SECONDS);
        }

        return $plan;
    }

    /**
     * Retrieve all plans for a merchant from cache or database.
     */
    public function getMerchantPlans(int $merchantId): Collection
    {
        $cacheKey = "merchant:{$merchantId}:plans";

        $plans = Cache::get($cacheKey);

        if ($plans instanceof Collection) {
            return $plans;
        }

        if ($plans !== null) {
            Cache::forget($cacheKey);
        }

        $plans = Plan::where('merchant_id', $merchantId)->get();

        Cache::put($cacheKey, $plans, self::TTL_SECONDS);

        return $plans;
    }

    /**
     * Invalidate cached pricing and configurations for a specific plan.
     */
    public function invalidate(Plan $plan): void
    {
        Cache::forget($this->getPlanCacheKey($plan->id));
        Cache::forget("merchant:{$plan->merchant_id}:plans");
    }

    /**
     * Invalidate by ID.
     */
    public function invalidateById(int $planId, ?int $merchantId = null): void
    {
        Cache::forget($this->getPlanCacheKey($planId));
        if ($merchantId !== null) {
            Cache::forget("merchant:{$merchantId}:plans");
        }
    }

    private function getPlanCacheKey(int $planId): string
    {
        return "plan:{$planId}";
    }
}
