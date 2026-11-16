<?php

namespace App\Billing;

use App\Models\Subscription;

/**
 * What CreateCheckoutSessionAction produced: either a URL to redirect the
 * browser to a hosted checkout page, or -- when the user already has an
 * active subscription and is just switching plans -- an in-place upgrade
 * that needed no new checkout at all.
 */
final class CheckoutOutcome
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $checkoutUrl = null,
        public readonly ?Subscription $subscription = null,
    ) {}

    public static function checkout(string $url): self
    {
        return new self('checkout', checkoutUrl: $url);
    }

    public static function upgraded(Subscription $subscription): self
    {
        return new self('upgraded', subscription: $subscription);
    }

    public static function alreadySubscribed(Subscription $subscription): self
    {
        return new self('already_subscribed', subscription: $subscription);
    }
}
