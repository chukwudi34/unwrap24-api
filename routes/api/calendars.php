<?php

use App\Http\Controllers\Api\CalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('calendars')->group(function () {
    Route::get('/', [CalendarController::class, 'index']);
    Route::post('/', [CalendarController::class, 'store']);
    Route::get('/{calendar}', [CalendarController::class, 'show']);
});
