<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\MerchantDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MerchantDashboardController extends Controller
{
    public function __construct(
        protected MerchantDashboardService $dashboardService
    ) {}

    /**
     * Display the merchant analytics dashboard (or return JSON).
     */
    public function show(Request $request, Merchant $merchant): View|JsonResponse
    {
        $dto = $this->dashboardService->getDashboardData($merchant);
        $data = $dto->toArray();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        $allMerchants = Merchant::orderBy('name')->get();

        return view('dashboard.merchant', [
            'merchant' => $merchant,
            'data' => $data,
            'allMerchants' => $allMerchants,
        ]);
    }
}
