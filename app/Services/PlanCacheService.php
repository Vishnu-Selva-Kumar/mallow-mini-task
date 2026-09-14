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

        return Cache::remember($cacheKey, self::TTL_SECONDS, function () use ($planId) {
            return Plan::find($planId);
        });
    }

    /**
     * Retrieve all plans for a merchant from cache or database.
     */
    public function getMerchantPlans(int $merchantId): Collection
    {
        $cacheKey = "merchant:{$merchantId}:plans";

        return Cache::remember($cacheKey, self::TTL_SECONDS, function () use ($merchantId) {
            return Plan::where('merchant_id', $merchantId)->get();
        });
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
