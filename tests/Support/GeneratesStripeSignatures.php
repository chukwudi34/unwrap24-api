<?php

namespace Tests\Support;

trait GeneratesStripeSignatures
{
    /**
     * Builds a `Stripe-Signature` header value that `Stripe\Webhook::constructEvent`
     * will accept, replicating the same v1=HMAC-SHA256(secret, "{t}.{payload}") scheme
     * Stripe itself uses -- so tests can exercise the real signature-verification path.
     */
    protected function stripeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
