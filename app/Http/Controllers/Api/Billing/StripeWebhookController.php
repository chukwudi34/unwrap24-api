<?php

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\ActivateSubscriptionFromCheckoutAction;
use App\Actions\Billing\RecordPaymentAction;
use App\Actions\Billing\SyncSubscriptionStatusAction;
use App\Billing\PaymentGatewayFactory;
use App\Billing\StripeGateway;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly ActivateSubscriptionFromCheckoutAction $activateSubscription,
        private readonly SyncSubscriptionStatusAction $syncSubscriptionStatus,
        private readonly RecordPaymentAction $recordPayment,
        private readonly PaymentGatewayFactory $gateways,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (\UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if (! $this->claimEvent($event)) {
            return response()->json(['status' => 'already_processed']);
        }

        $this->process($event);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Atomically claims this event id so a retried delivery (Stripe sends
     * webhooks at-least-once) is a no-op the second time around.
     */
    private function claimEvent(Event $event): bool
    {
        try {
            WebhookEvent::create([
                'provider' => 'stripe',
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);

            return true;
        } catch (QueryException $exception) {
            // SQLSTATE 23000 is the portable "integrity constraint violation"
            // code for a unique-index collision on both MySQL and SQLite.
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return false;
            }

            throw $exception;
        }
    }

    private function process(Event $event): void
    {
        $gateway = $this->gateways->default();

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.updated', 'customer.subscription.deleted' => $this->syncSubscriptionStatus->handle(
                $gateway->provider(),
                $event->data->object->id,
                StripeGateway::mapSubscription($event->data->object),
            ),
            'invoice.paid', 'invoice.payment_failed' => $this->handleInvoiceEvent($event),
            default => null,
        };
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $subscriptionId = is_string($session->subscription) ? $session->subscription : ($session->subscription->id ?? null);
        $customerId = is_string($session->customer) ? $session->customer : ($session->customer->id ?? null);
        $metadata = $session->metadata ? $session->metadata->toArray() : [];

        if (! $subscriptionId) {
            return;
        }

        $gateway = $this->gateways->default();
        $state = $gateway->findSubscription($subscriptionId);

        if (! $state) {
            return;
        }

        $this->activateSubscription->handle($gateway->provider(), $subscriptionId, $customerId, $metadata, $state);
    }

    private function handleInvoiceEvent(Event $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->data->object;

        $subscriptionRef = $invoice->parent->subscription_details->subscription ?? null;
        $subscriptionId = is_string($subscriptionRef) ? $subscriptionRef : ($subscriptionRef->id ?? null);

        $paidAtTimestamp = $invoice->status_transitions->paid_at ?? null;
        $paidAt = $paidAtTimestamp ? (new DateTimeImmutable)->setTimestamp($paidAtTimestamp) : null;

        $status = $event->type === 'invoice.paid' ? 'succeeded' : 'failed';
        $amount = $event->type === 'invoice.paid' ? $invoice->amount_paid : $invoice->amount_due;

        $this->recordPayment->handle(
            provider: 'stripe',
            providerReference: $invoice->id,
            status: $status,
            amountMinorUnits: $amount,
            currency: $invoice->currency,
            providerSubscriptionId: $subscriptionId,
            paidAt: $paidAt,
        );
    }
}
