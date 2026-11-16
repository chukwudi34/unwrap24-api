<?php

namespace App\Billing;

use DateTimeImmutable;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

final class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly StripeClient $client) {}

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession
    {
        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'price_data' => [
                    'currency' => $data->pricing->currency,
                    'unit_amount' => $data->pricing->unitAmount,
                    'recurring' => ['interval' => $data->pricing->interval],
                    'product_data' => ['name' => $data->pricing->productName],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'client_reference_id' => $data->clientReferenceId,
            'metadata' => $data->metadata,
            'subscription_data' => ['metadata' => $data->metadata],
        ];

        if ($data->existingCustomerId) {
            $params['customer'] = $data->existingCustomerId;
        } else {
            $params['customer_email'] = $data->customerEmail;
        }

        $session = $this->client->checkout->sessions->create($params);

        return self::mapCheckoutSession($session);
    }

    public function findCheckoutSession(string $sessionId): ?CheckoutSession
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException) {
            return null;
        }

        return self::mapCheckoutSession($session);
    }

    public function findSubscription(string $subscriptionId): ?GatewaySubscription
    {
        try {
            $subscription = $this->client->subscriptions->retrieve($subscriptionId);
        } catch (ApiErrorException) {
            return null;
        }

        return self::mapSubscription($subscription);
    }

    public function updateSubscriptionPricing(string $subscriptionId, PlanPricing $pricing): GatewaySubscription
    {
        $current = $this->client->subscriptions->retrieve($subscriptionId);
        $itemId = $current->items->data[0]->id;

        $updated = $this->client->subscriptions->update($subscriptionId, [
            'items' => [[
                'id' => $itemId,
                'price_data' => [
                    'currency' => $pricing->currency,
                    'unit_amount' => $pricing->unitAmount,
                    'recurring' => ['interval' => $pricing->interval],
                    'product_data' => ['name' => $pricing->productName],
                ],
            ]],
            'proration_behavior' => 'create_prorations',
        ]);

        return self::mapSubscription($updated);
    }

    public static function mapCheckoutSession(StripeCheckoutSession $session): CheckoutSession
    {
        return new CheckoutSession(
            id: $session->id,
            url: $session->url,
            status: $session->status,
            paymentStatus: $session->payment_status,
            customerId: is_string($session->customer) ? $session->customer : ($session->customer->id ?? null),
            subscriptionId: is_string($session->subscription) ? $session->subscription : ($session->subscription->id ?? null),
            metadata: $session->metadata ? $session->metadata->toArray() : [],
        );
    }

    public static function mapSubscription(StripeSubscription $subscription): GatewaySubscription
    {
        $item = $subscription->items->data[0] ?? null;

        return new GatewaySubscription(
            id: $subscription->id,
            status: $subscription->status,
            currentPeriodStart: $item?->current_period_start ? (new DateTimeImmutable)->setTimestamp($item->current_period_start) : null,
            currentPeriodEnd: $item?->current_period_end ? (new DateTimeImmutable)->setTimestamp($item->current_period_end) : null,
            cancelAtPeriodEnd: (bool) $subscription->cancel_at_period_end,
            customerId: is_string($subscription->customer) ? $subscription->customer : ($subscription->customer->id ?? null),
        );
    }
}
