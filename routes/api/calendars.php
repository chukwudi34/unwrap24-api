<?php

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CalendarDraftController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('calendars')->group(function () {
    Route::get('/', [CalendarController::class, 'index']);
    Route::post('/', [CalendarController::class, 'store']);
    Route::get('/draft', [CalendarDraftController::class, 'show']);
    Route::put('/draft', [CalendarDraftController::class, 'update']);
    Route::delete('/draft', [CalendarDraftController::class, 'destroy']);
    Route::get('/{calendar}', [CalendarController::class, 'show']);
});
