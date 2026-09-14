<?php

namespace App\Services;

use App\DTOs\RecordUsageDTO;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UsageService
{
    /**
     * Record a user usage event with strict idempotency.
     *
     * @return array{event: UsageEvent, is_duplicate: bool}
     */
    public function recordUsage(RecordUsageDTO $dto): array
    {
        // 1. Idempotency Check: if already recorded, return immediately without double-counting
        $existingEvent = UsageEvent::where('idempotency_key', $dto->idempotencyKey)->first();
        if ($existingEvent !== null) {
            return [
                'event' => $existingEvent,
                'is_duplicate' => true,
            ];
        }

        // 2. Check user existence
        $user = User::find($dto->userId);
        if ($user === null) {
            throw new NotFoundHttpException("User with ID {$dto->userId} not found.");
        }

        // 3. Check active subscription for user
        $subscription = Subscription::where('user_id', $dto->userId)
            ->where('status', 'active')
            ->first();

        if ($subscription === null) {
            throw new UnprocessableEntityHttpException("User {$dto->userId} has no active subscription.");
        }

        // 4. Insert usage event safely under race conditions
        try {
            $event = UsageEvent::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'usage_date' => $dto->usageDate,
                'units' => $dto->units,
                'idempotency_key' => $dto->idempotencyKey,
            ]);

            return [
                'event' => $event,
                'is_duplicate' => false,
            ];
        } catch (UniqueConstraintViolationException) {
            // Race condition fallback: return existing event recorded concurrently
            $event = UsageEvent::where('idempotency_key', $dto->idempotencyKey)->firstOrFail();

            return [
                'event' => $event,
                'is_duplicate' => true,
            ];
        }
    }
}
