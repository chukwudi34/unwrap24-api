<?php

use App\Http\Controllers\Api\PublicCalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public calendar routes (no auth)
|--------------------------------------------------------------------------
|
| The shareable /c/{slug} page: reading a published calendar, unlocking
| a password-protected one, and recording visit/open/share analytics.
|
*/

Route::prefix('public/calendars')->group(function () {
    Route::get('/{slug}', [PublicCalendarController::class, 'show']);
    Route::post('/{slug}/unlock', [PublicCalendarController::class, 'unlock']);
    Route::post('/{slug}/visit', [PublicCalendarController::class, 'visit']);
    Route::post('/{slug}/open', [PublicCalendarController::class, 'open']);
    Route::post('/{slug}/share', [PublicCalendarController::class, 'share']);
});
