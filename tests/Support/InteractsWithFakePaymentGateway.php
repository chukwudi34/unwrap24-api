<?php

namespace Tests\Support;

use App\Billing\PaymentGateway;
use App\Billing\PaymentGatewayFactory;

trait InteractsWithFakePaymentGateway
{
    protected function fakePaymentGateway(): FakePaymentGateway
    {
        $fake = new FakePaymentGateway;

        $this->app->instance(PaymentGatewayFactory::class, new class($fake) extends PaymentGatewayFactory
        {
            public function __construct(private readonly FakePaymentGateway $fake) {}

            public function for(string $provider): PaymentGateway
            {
                return $this->fake;
            }

            public function default(): PaymentGateway
            {
                return $this->fake;
            }
        });

        return $fake;
    }
}
