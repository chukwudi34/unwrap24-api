<?php

namespace App\Billing;

final class CheckoutSession
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $url,
        public readonly string $status,
        public readonly ?string $paymentStatus,
        public readonly ?string $customerId,
        public readonly ?string $subscriptionId,
        public readonly array $metadata = [],
    ) {}
}
