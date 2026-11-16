<?php

namespace App\Billing;

use App\Billing\Exceptions\UnsupportedProviderException;
use Stripe\StripeClient;

/**
 * The default/primary provider new subscriptions are created against.
 * 'paystack' is a valid value in the `provider` DB enums and can be read
 * back out for existing rows, but PaymentGatewayFactory::for('paystack')
 * intentionally isn't implemented yet.
 */
class PaymentGatewayFactory
{
    public const DEFAULT_PROVIDER = 'stripe';

    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            'stripe' => new StripeGateway(new StripeClient(config('services.stripe.secret'))),
            'paystack' => throw new UnsupportedProviderException(
                'Paystack is not wired up yet. The schema supports it (payments.provider / subscriptions.provider), '.
                'but PaymentGatewayFactory has no Paystack implementation.',
            ),
            default => throw new UnsupportedProviderException("Unknown payment provider [{$provider}]."),
        };
    }

    public function default(): PaymentGateway
    {
        return $this->for(self::DEFAULT_PROVIDER);
    }
}
