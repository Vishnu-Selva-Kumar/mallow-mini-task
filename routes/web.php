<?php

use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/usage', [UsageController::class, 'store'])->middleware('throttle:usage-metering');
