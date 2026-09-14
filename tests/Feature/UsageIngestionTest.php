<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;
    protected Plan $plan;
    protected User $user;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create(['name' => 'Acme Ingestion Corp']);

        $this->plan = Plan::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Standard Plan',
            'base_price' => 1500.00,
            'billing_cycle' => 'monthly',
            'included_units' => 2000,
            'overage_rate' => 0.25,
        ]);

        $this->user = User::create([
            'name' => 'Jane Ingestion',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_it_successfully_ingests_usage_event_and_returns_201(): void
    {
        $payload = [
            'user_id' => $this->user->id,
            'usage_date' => '2026-09-14',
            'units' => 150,
            'idempotency_key' => 'idem-key-001',
        ];

        $response = $this->postJson('/api/usage', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Usage event recorded successfully.',
                'data' => [
                    'user_id' => $this->user->id,
                    'subscription_id' => $this->subscription->id,
                    'usage_date' => '2026-09-14',
                    'units' => 150,
                    'idempotency_key' => 'idem-key-001',
                ],
            ]);

        $this->assertDatabaseHas('usage_events', [
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'usage_date' => '2026-09-14',
            'units' => 150,
            'idempotency_key' => 'idem-key-001',
        ]);
    }

    public function test_it_replays_idempotent_event_with_200_ok_and_does_not_create_duplicate(): void
    {
        $payload = [
            'user_id' => $this->user->id,
            'usage_date' => '2026-09-14',
            'units' => 200,
            'idempotency_key' => 'idem-replay-002',
        ];

        // First call
        $firstResponse = $this->postJson('/api/usage', $payload);
        $firstResponse->assertStatus(201);

        // Second call with same idempotency key
        $secondResponse = $this->postJson('/api/usage', $payload);
        $secondResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Usage event already recorded (idempotent response).',
                'data' => [
                    'user_id' => $this->user->id,
                    'subscription_id' => $this->subscription->id,
                    'units' => 200,
                    'idempotency_key' => 'idem-replay-002',
                ],
            ]);

        // Verify only 1 record exists in DB
        $this->assertEquals(
            1,
            UsageEvent::where('idempotency_key', 'idem-replay-002')->count()
        );
    }

    public function test_it_fails_validation_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/usage', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'usage_date', 'units', 'idempotency_key']);
    }

    public function test_it_fails_validation_when_units_are_non_positive(): void
    {
        $response = $this->postJson('/api/usage', [
            'user_id' => $this->user->id,
            'usage_date' => '2026-09-14',
            'units' => 0,
            'idempotency_key' => 'key-zero-units',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['units']);
    }

    public function test_it_fails_when_user_has_no_active_subscription(): void
    {
        $otherUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->postJson('/api/usage', [
            'user_id' => $otherUser->id,
            'usage_date' => '2026-09-14',
            'units' => 50,
            'idempotency_key' => 'key-no-sub',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_enforces_rate_limiting_at_120_requests(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $response = $this->postJson('/api/usage', [
                'user_id' => $this->user->id,
                'usage_date' => '2026-09-14',
                'units' => 1,
                'idempotency_key' => "rate-limit-key-{$i}",
            ]);
            $response->assertStatus(201);
        }

        // 121st request should be throttled
        $throttledResponse = $this->postJson('/api/usage', [
            'user_id' => $this->user->id,
            'usage_date' => '2026-09-14',
            'units' => 1,
            'idempotency_key' => 'rate-limit-key-121',
        ]);

        $throttledResponse->assertStatus(429);
    }
}
