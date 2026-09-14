<?php

namespace App\Http\Controllers;

use App\DTOs\RecordUsageDTO;
use App\Http\Requests\StoreUsageRequest;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UsageController extends Controller
{
    /**
     * Store a newly recorded usage event.
     */
    public function store(StoreUsageRequest $request, UsageService $usageService): JsonResponse
    {
        $dto = RecordUsageDTO::fromRequest($request);
        $result = $usageService->recordUsage($dto);

        $statusCode = $result['is_duplicate'] 
            ? Response::HTTP_OK 
            : Response::HTTP_CREATED;

        return response()->json([
            'success' => true,
            'message' => $result['is_duplicate']
                ? 'Usage event already recorded (idempotent response).'
                : 'Usage event recorded successfully.',
            'data' => [
                'id' => $result['event']->id,
                'user_id' => $result['event']->user_id,
                'subscription_id' => $result['event']->subscription_id,
                'usage_date' => $result['event']->usage_date->toDateString(),
                'units' => $result['event']->units,
                'idempotency_key' => $result['event']->idempotency_key,
                'created_at' => $result['event']->created_at->toISOString(),
            ],
        ], $statusCode);
    }
}
