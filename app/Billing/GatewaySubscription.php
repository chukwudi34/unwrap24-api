<?php

namespace App\Billing;

use DateTimeImmutable;

final class GatewaySubscription
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?DateTimeImmutable $currentPeriodStart,
        public readonly ?DateTimeImmutable $currentPeriodEnd,
        public readonly bool $cancelAtPeriodEnd,
        public readonly ?string $customerId = null,
    ) {}
}
