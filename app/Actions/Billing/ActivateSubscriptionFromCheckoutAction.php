<?php

namespace App\Actions\Billing;

use App\Billing\GatewaySubscription;
use App\Models\Subscription;
use App\Models\User;

/**
 * Handles the `checkout.session.completed` webhook: the only place a
 * Subscription row is first created. Nothing is activated anywhere else --
 * the redirect back to our own success page never writes to the database.
 */
class ActivateSubscriptionFromCheckoutAction
{
    /**
     * @param  array<string, string>  $metadata  user_id, plan_id, organization_id?, interval
     */
    public function handle(string $provider, string $providerSubscriptionId, ?string $customerId, array $metadata, GatewaySubscription $state): ?Subscription
    {
        $userId = $metadata['user_id'] ?? null;
        $planId = $metadata['plan_id'] ?? null;

        if (! $userId || ! $planId) {
            return null;
        }

        if ($customerId) {
            User::whereKey($userId)->whereNull('stripe_customer_id')->update(['stripe_customer_id' => $customerId]);
        }

        return Subscription::updateOrCreate(
            ['provider' => $provider, 'provider_subscription_id' => $providerSubscriptionId],
            [
                'user_id' => $userId,
                'organization_id' => $metadata['organization_id'] ?? null,
                'plan_id' => $planId,
                'interval' => $metadata['interval'] ?? 'month',
                'status' => $state->status,
                'current_period_start' => $state->currentPeriodStart,
                'current_period_end' => $state->currentPeriodEnd,
                'cancel_at_period_end' => $state->cancelAtPeriodEnd,
            ],
        );
    }
}
