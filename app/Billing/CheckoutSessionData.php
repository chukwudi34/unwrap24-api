<?php

namespace App\Billing;

final class CheckoutSessionData
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $customerEmail,
        public readonly ?string $existingCustomerId,
        public readonly PlanPricing $pricing,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly string $clientReferenceId,
        public readonly array $metadata = [],
    ) {}
}
