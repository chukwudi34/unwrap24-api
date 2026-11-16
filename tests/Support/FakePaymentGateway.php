<?php

namespace Tests\Support;

use App\Billing\CheckoutSession;
use App\Billing\CheckoutSessionData;
use App\Billing\GatewaySubscription;
use App\Billing\PaymentGateway;
use App\Billing\PlanPricing;

/**
 * A controllable PaymentGateway test double. Configure the queued return
 * values before exercising the action/controller under test, then assert
 * on `$calls` / `$updatedPricing` to verify what would have been sent to
 * the real gateway.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var list<array{method: string, args: array<mixed>}> */
    public array $calls = [];

    public ?CheckoutSession $nextCheckoutSession = null;

    public ?CheckoutSession $findCheckoutSessionResult = null;

    public ?GatewaySubscription $findSubscriptionResult = null;

    public ?GatewaySubscription $updateSubscriptionPricingResult = null;

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession
    {
        $this->calls[] = ['method' => 'createCheckoutSession', 'args' => [$data]];

        return $this->nextCheckoutSession ?? new CheckoutSession(
            id: 'cs_test_fake',
            url: 'https://checkout.stripe.com/c/pay/cs_test_fake',
            status: 'open',
            paymentStatus: 'unpaid',
            customerId: null,
            subscriptionId: null,
            metadata: $data->metadata,
        );
    }

    public function findCheckoutSession(string $sessionId): ?CheckoutSession
    {
        $this->calls[] = ['method' => 'findCheckoutSession', 'args' => [$sessionId]];

        return $this->findCheckoutSessionResult;
    }

    public function findSubscription(string $subscriptionId): ?GatewaySubscription
    {
        $this->calls[] = ['method' => 'findSubscription', 'args' => [$subscriptionId]];

        return $this->findSubscriptionResult;
    }

    public function updateSubscriptionPricing(string $subscriptionId, PlanPricing $pricing): GatewaySubscription
    {
        $this->calls[] = ['method' => 'updateSubscriptionPricing', 'args' => [$subscriptionId, $pricing]];

        return $this->updateSubscriptionPricingResult ?? new GatewaySubscription(
            id: $subscriptionId,
            status: 'active',
            currentPeriodStart: new \DateTimeImmutable,
            currentPeriodEnd: new \DateTimeImmutable('+1 month'),
            cancelAtPeriodEnd: false,
        );
    }
}
