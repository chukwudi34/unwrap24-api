<?php

namespace App\Actions\Billing;

use App\Billing\GatewaySubscription;
use App\Models\Subscription;

/**
 * Handles `customer.subscription.updated` / `.deleted`: keeps an existing
 * Subscription row's status and billing period in sync with the gateway.
 * Never creates a row -- if nothing matches `provider_subscription_id` yet,
 * `checkout.session.completed` hasn't landed, so there is nothing to update.
 */
class SyncSubscriptionStatusAction
{
    public function handle(string $provider, string $providerSubscriptionId, GatewaySubscription $state): ?Subscription
    {
        $subscription = Subscription::where('provider', $provider)
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if (! $subscription) {
            return null;
        }

        $subscription->update([
            'status' => $state->status,
            'current_period_start' => $state->currentPeriodStart,
            'current_period_end' => $state->currentPeriodEnd,
            'cancel_at_period_end' => $state->cancelAtPeriodEnd,
        ]);

        return $subscription;
    }
}
