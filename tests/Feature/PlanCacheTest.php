<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Plan;
use App\Services\PlanCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlanCacheTest extends TestCase
{
    use RefreshDatabase;

    protected PlanCacheService $cacheService;
    protected Merchant $merchant;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new PlanCacheService();

        $this->merchant = Merchant::create(['name' => 'Cache Test Corp']);

        $this->plan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Cacheable Tier',
            'base_price' => 500.00,
            'billing_cycle' => 'monthly',
            'included_units' => 1000,
            'overage_rate' => 0.10,
        ]);
    }

    public function test_it_caches_plan_and_retrieves_from_cache(): void
    {
        $cacheKey = "plan:{$this->plan->id}";

        $this->assertFalse(Cache::has($cacheKey));

        $retrievedPlan = $this->cacheService->getPlan($this->plan->id);

        $this->assertNotNull($retrievedPlan);
        $this->assertEquals($this->plan->id, $retrievedPlan->id);
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_it_invalidates_plan_cache_properly(): void
    {
        $cacheKey = "plan:{$this->plan->id}";

        // Prime cache
        $this->cacheService->getPlan($this->plan->id);
        $this->assertTrue(Cache::has($cacheKey));

        // Invalidate
        $this->cacheService->invalidate($this->plan);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_it_caches_and_invalidates_merchant_plans_collection(): void
    {
        $merchantCacheKey = "merchant:{$this->merchant->id}:plans";

        $this->assertFalse(Cache::has($merchantCacheKey));

        $plans = $this->cacheService->getMerchantPlans($this->merchant->id);

        $this->assertCount(1, $plans);
        $this->assertTrue(Cache::has($merchantCacheKey));

        // Invalidate via plan
        $this->cacheService->invalidate($this->plan);
        $this->assertFalse(Cache::has($merchantCacheKey));
    }
}
