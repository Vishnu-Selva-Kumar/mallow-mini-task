<?php

namespace App\DTOs;

use App\Http\Requests\StoreUsageRequest;

readonly class RecordUsageDTO
{
    public function __construct(
        public int $userId,
        public string $usageDate,
        public int $units,
        public string $idempotencyKey,
    ) {}

    public static function fromRequest(StoreUsageRequest $request): self
    {
        return new self(
            userId: (int) $request->validated('user_id'),
            usageDate: (string) $request->validated('usage_date'),
            units: (int) $request->validated('units'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'usage_date' => $this->usageDate,
            'units' => $this->units,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
