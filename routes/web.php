<?php

use App\Http\Controllers\MerchantDashboardController;
use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show'])->name('merchants.dashboard');
Route::get('/api/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show'])->name('api.merchants.dashboard');

Route::post('/api/usage', [UsageController::class, 'store'])->middleware('throttle:usage-metering');

