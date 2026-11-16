<?php

namespace App\Billing;

/**
 * The amount actually quoted to the gateway for one checkout/subscription
 * change. Built fresh from the `plans` table on every request -- plans are
 * never pre-registered as Stripe/Paystack Products, so pricing always
 * reflects whatever is in our database at the moment of checkout.
 */
final class PlanPricing
{
    public function __construct(
        public readonly string $productName,
        public readonly int $unitAmount,
        public readonly string $currency,
        public readonly string $interval,
    ) {}
}
