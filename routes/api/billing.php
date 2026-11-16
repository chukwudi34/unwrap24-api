<?php

use App\Http\Controllers\Api\Billing\CheckoutController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Called directly by Stripe, never by the frontend -- no auth, verified via
// the Stripe-Signature header instead (see StripeWebhookController).
Route::post('/billing/webhook/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->prefix('billing')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/session/{sessionId}', [CheckoutController::class, 'session']);
    Route::get('/subscription', [SubscriptionController::class, 'show']);
});
