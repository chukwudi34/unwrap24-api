<?php

namespace Tests\Feature\Billing;

use App\Billing\GatewaySubscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\GeneratesStripeSignatures;
use Tests\Support\InteractsWithFakePaymentGateway;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use GeneratesStripeSignatures, InteractsWithFakePaymentGateway, RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    private function postWebhook(array $event, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event);
        $signature ??= $this->stripeSignatureHeader($payload, self::SECRET);

        return $this->call('POST', '/api/billing/webhook/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => $signature,
        ], $payload);
    }

    private function checkoutCompletedEvent(string $eventId, User $user, Plan $plan, string $subscriptionId = 'sub_123', string $customerId = 'cus_123'): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'customer' => $customerId,
                'subscription' => $subscriptionId,
                'payment_status' => 'paid',
                'status' => 'complete',
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'interval' => 'month',
                ],
            ]],
        ];
    }

    public function test_it_rejects_a_webhook_with_an_invalid_signature(): void
    {
        $event = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => []]];

        $this->postWebhook($event, 'garbage-signature')->assertStatus(400);
    }

    public function test_checkout_session_completed_activates_a_subscription(): void
    {
        $fake = $this->fakePaymentGateway();
        $fake->findSubscriptionResult = new GatewaySubscription(
            id: 'sub_123',
            status: 'active',
            currentPeriodStart: new \DateTimeImmutable('2026-11-01'),
            currentPeriodEnd: new \DateTimeImmutable('2026-12-01'),
            cancelAtPeriodEnd: false,
        );

        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);

        $response = $this->postWebhook($this->checkoutCompletedEvent('evt_1', $user, $plan));

        $response->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_123',
            'status' => 'active',
        ]);

        $this->assertSame('cus_123', $user->fresh()->stripe_customer_id);
    }

    public function test_replaying_the_same_event_id_is_a_no_op(): void
    {
        $fake = $this->fakePaymentGateway();
        $fake->findSubscriptionResult = new GatewaySubscription(
            id: 'sub_123',
            status: 'active',
            currentPeriodStart: new \DateTimeImmutable('2026-11-01'),
            currentPeriodEnd: new \DateTimeImmutable('2026-12-01'),
            cancelAtPeriodEnd: false,
        );

        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);
        $event = $this->checkoutCompletedEvent('evt_duplicate', $user, $plan);

        $this->postWebhook($event)->assertStatus(200);
        $this->assertCount(1, $fake->calls);
        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, Subscription::count());

        $this->postWebhook($event)->assertStatus(200)->assertJsonPath('status', 'already_processed');

        // No second call to the gateway, and no second (or mutated) subscription row.
        $this->assertCount(1, $fake->calls);
        $this->assertSame(1, Subscription::count());
    }

    public function test_subscription_updated_syncs_status_and_period_on_an_existing_row(): void
    {
        $this->fakePaymentGateway();
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'trialing',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_123',
        ]);

        $event = [
            'id' => 'evt_2',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_123',
                'object' => 'subscription',
                'status' => 'active',
                'cancel_at_period_end' => true,
                'customer' => 'cus_123',
                'items' => ['object' => 'list', 'data' => [[
                    'id' => 'si_123',
                    'object' => 'subscription_item',
                    'current_period_start' => 1764547200,
                    'current_period_end' => 1767225600,
                ]]],
            ]],
        ];

        $this->postWebhook($event)->assertStatus(200);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue((bool) $subscription->cancel_at_period_end);
        $this->assertNotNull($subscription->current_period_end);
    }

    public function test_invoice_paid_records_a_payment_idempotently(): void
    {
        $this->fakePaymentGateway();
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_123',
        ]);

        $event = [
            'id' => 'evt_3',
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_123',
                'object' => 'invoice',
                'amount_paid' => 900,
                'amount_due' => 900,
                'currency' => 'usd',
                'status' => 'paid',
                'status_transitions' => ['paid_at' => 1764547200],
                'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_123']],
            ]],
        ];

        $this->postWebhook($event)->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_reference' => 'in_123',
            'user_id' => $user->id,
            'status' => 'succeeded',
            'amount' => 9.00,
            'currency' => 'USD',
        ]);
        $this->assertSame(1, Payment::count());

        // A retried webhook for the same invoice must not create a second row.
        $this->postWebhook(['id' => 'evt_3_retry'] + $event)->assertStatus(200);
        $this->assertSame(1, Payment::count());
    }

    public function test_invoice_payment_failed_records_a_failed_payment(): void
    {
        $this->fakePaymentGateway();
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'past_due',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_123',
        ]);

        $event = [
            'id' => 'evt_4',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_456',
                'object' => 'invoice',
                'amount_paid' => 0,
                'amount_due' => 900,
                'currency' => 'usd',
                'status' => 'open',
                'status_transitions' => ['paid_at' => null],
                'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_123']],
            ]],
        ];

        $this->postWebhook($event)->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_reference' => 'in_456',
            'status' => 'failed',
        ]);
    }
}
