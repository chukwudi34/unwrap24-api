<?php

namespace App\Billing;

/**
 * A provider-agnostic surface over a hosted-checkout payment gateway
 * (Stripe today, Paystack later -- see PaymentGatewayFactory). Webhook
 * payload parsing is intentionally NOT part of this interface: the two
 * providers' event shapes differ enough that normalizing them here would
 * be premature abstraction. Each provider gets its own webhook controller,
 * which both funnel into the same Billing actions to update local state.
 */
interface PaymentGateway
{
    public function provider(): string;

    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession;

    public function findCheckoutSession(string $sessionId): ?CheckoutSession;

    public function findSubscription(string $subscriptionId): ?GatewaySubscription;

    /**
     * Change an existing subscription to new pricing in place (with
     * proration), rather than creating a second subscription. Used for the
     * "existing subscriber upgrades" flow.
     */
    public function updateSubscriptionPricing(string $subscriptionId, PlanPricing $pricing): GatewaySubscription;
}
