<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use DateTimeImmutable;

/**
 * Handles `invoice.paid` / `invoice.payment_failed`: upserts a Payment row
 * keyed by (provider, provider_reference), which carries a DB-level unique
 * index -- so a retried webhook for the same invoice can never double-record
 * the same charge.
 */
class RecordPaymentAction
{
    public function handle(
        string $provider,
        string $providerReference,
        string $status,
        int $amountMinorUnits,
        string $currency,
        ?string $providerSubscriptionId,
        ?DateTimeImmutable $paidAt,
    ): ?Payment {
        $subscription = $providerSubscriptionId
            ? Subscription::where('provider', $provider)->where('provider_subscription_id', $providerSubscriptionId)->first()
            : null;

        $userId = $subscription?->user_id;

        if (! $userId) {
            return null;
        }

        return Payment::updateOrCreate(
            ['provider' => $provider, 'provider_reference' => $providerReference],
            [
                'subscription_id' => $subscription?->id,
                'user_id' => $userId,
                'amount' => $amountMinorUnits / 100,
                'currency' => strtoupper($currency),
                'status' => $status,
                'paid_at' => $paidAt,
            ],
        );
    }
}
