<?php

use App\Http\Controllers\Api\DashboardStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/stats', DashboardStatsController::class);
});
