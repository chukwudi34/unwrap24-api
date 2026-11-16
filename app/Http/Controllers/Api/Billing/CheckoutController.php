<?php

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\CreateCheckoutSessionAction;
use App\Billing\PaymentGatewayFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CreateCheckoutSessionAction $createCheckoutSession,
        private readonly PaymentGatewayFactory $gateways,
    ) {}

    public function store(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $outcome = $this->createCheckoutSession->handle($request->user(), $request->validated());

        return match ($outcome->type) {
            'checkout' => response()->json(['type' => 'checkout', 'checkout_url' => $outcome->checkoutUrl]),
            'upgraded' => response()->json([
                'type' => 'upgraded',
                'message' => 'Your subscription has been updated.',
                'data' => new SubscriptionResource($outcome->subscription->load('plan')),
            ]),
            'already_subscribed' => response()->json([
                'type' => 'already_subscribed',
                'message' => "You're already on this plan.",
                'data' => new SubscriptionResource($outcome->subscription->load('plan')),
            ]),
        };
    }

    /**
     * Polled by the /billing/success page after the Stripe redirect. This
     * is a read-only status check -- it asks Stripe directly (never trusts
     * the redirect itself) and reports whether our own webhook has already
     * activated the subscription locally. Only the webhook ever writes.
     */
    public function session(Request $request, string $sessionId): JsonResponse
    {
        $gateway = $this->gateways->default();
        $session = $gateway->findCheckoutSession($sessionId);

        if (! $session) {
            return response()->json(['message' => 'Checkout session not found.'], 404);
        }

        $ownerId = $session->metadata['user_id'] ?? null;
        if ((string) $ownerId !== (string) $request->user()->id) {
            throw ValidationException::withMessages(['session_id' => 'This checkout session does not belong to you.']);
        }

        $subscription = $session->subscriptionId
            ? Subscription::where('provider', $gateway->provider())
                ->where('provider_subscription_id', $session->subscriptionId)
                ->with('plan')
                ->first()
            : null;

        return response()->json([
            'data' => [
                'session_status' => $session->status,
                'payment_status' => $session->paymentStatus,
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            ],
        ]);
    }
}
