<?php

namespace Tests\Feature\Billing;

use App\Billing\CheckoutSession;
use App\Billing\GatewaySubscription;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFakePaymentGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use InteractsWithFakePaymentGateway, RefreshDatabase;

    private function creatorPlan(): Plan
    {
        return Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9, 'price_yearly' => 90, 'currency' => 'USD']);
    }

    private function businessPlan(): Plan
    {
        return Plan::create(['name' => 'Business', 'slug' => 'business', 'price_monthly' => 29, 'price_yearly' => 290, 'currency' => 'USD']);
    }

    private function freePlan(): Plan
    {
        return Plan::create(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0]);
    }

    public function test_guests_cannot_start_checkout(): void
    {
        $this->creatorPlan();

        $this->postJson('/api/billing/checkout', ['plan_slug' => 'creator', 'interval' => 'month'])
            ->assertStatus(401);
    }

    public function test_it_creates_a_checkout_session_with_monthly_pricing_in_cents(): void
    {
        $fake = $this->fakePaymentGateway();
        $fake->nextCheckoutSession = new CheckoutSession(
            id: 'cs_test_1',
            url: 'https://checkout.stripe.com/c/pay/cs_test_1',
            status: 'open',
            paymentStatus: 'unpaid',
            customerId: null,
            subscriptionId: null,
        );

        $plan = $this->creatorPlan();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'creator',
            'interval' => 'month',
        ]);

        $response->assertStatus(200)
            ->assertJson(['type' => 'checkout', 'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);

        $this->assertCount(1, $fake->calls);
        $sentData = $fake->calls[0]['args'][0];
        $this->assertSame(900, $sentData->pricing->unitAmount);
        $this->assertSame('usd', $sentData->pricing->currency);
        $this->assertSame('month', $sentData->pricing->interval);
        $this->assertSame((string) $user->id, $sentData->metadata['user_id']);
        $this->assertSame((string) $plan->id, $sentData->metadata['plan_id']);

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_it_uses_yearly_pricing_when_requested(): void
    {
        $fake = $this->fakePaymentGateway();
        $this->creatorPlan();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'creator',
            'interval' => 'year',
        ])->assertStatus(200);

        $sentData = $fake->calls[0]['args'][0];
        $this->assertSame(9000, $sentData->pricing->unitAmount);
        $this->assertSame('year', $sentData->pricing->interval);
    }

    public function test_it_rejects_checkout_for_a_non_purchasable_plan(): void
    {
        $this->fakePaymentGateway();
        $this->freePlan();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'free',
            'interval' => 'month',
        ])->assertStatus(422)->assertJsonValidationErrors('plan_slug');
    }

    public function test_business_plan_requires_an_organization_the_user_owns(): void
    {
        $this->fakePaymentGateway();
        $this->businessPlan();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $othersOrg = Organization::create(['name' => "Other's Org", 'slug' => 'others-org', 'owner_id' => $otherUser->id]);

        // No organization_id at all.
        $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'business',
            'interval' => 'month',
        ])->assertStatus(422)->assertJsonValidationErrors('organization_id');

        // An organization that exists but belongs to someone else.
        $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'business',
            'interval' => 'month',
            'organization_id' => $othersOrg->ulid,
        ])->assertStatus(422)->assertJsonValidationErrors('organization_id');
    }

    public function test_business_plan_checkout_succeeds_for_an_organization_the_user_owns(): void
    {
        $fake = $this->fakePaymentGateway();
        $this->businessPlan();
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'My Org', 'slug' => 'my-org', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'business',
            'interval' => 'month',
            'organization_id' => $org->ulid,
        ]);

        $response->assertStatus(200)->assertJsonPath('type', 'checkout');

        $sentData = $fake->calls[0]['args'][0];
        $this->assertSame((string) $org->id, $sentData->metadata['organization_id']);
    }

    public function test_selecting_the_same_plan_twice_reports_already_subscribed_without_a_new_checkout(): void
    {
        $fake = $this->fakePaymentGateway();
        $plan = $this->creatorPlan();
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_existing',
        ]);

        $response = $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'creator',
            'interval' => 'month',
        ]);

        $response->assertStatus(200)->assertJsonPath('type', 'already_subscribed');
        $this->assertCount(0, $fake->calls);
    }

    public function test_switching_plans_while_already_subscribed_upgrades_in_place_instead_of_creating_a_new_checkout(): void
    {
        $fake = $this->fakePaymentGateway();
        $fake->updateSubscriptionPricingResult = new GatewaySubscription(
            id: 'sub_existing',
            status: 'active',
            currentPeriodStart: new \DateTimeImmutable('2026-11-01'),
            currentPeriodEnd: new \DateTimeImmutable('2026-12-01'),
            cancelAtPeriodEnd: false,
        );

        $creator = $this->creatorPlan();
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $creator->id,
            'interval' => 'month',
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_existing',
        ]);

        // Already on Creator monthly; switching to Creator yearly should
        // update the existing subscription in place rather than starting
        // a second one.
        $response = $this->actingAs($user)->postJson('/api/billing/checkout', [
            'plan_slug' => 'creator',
            'interval' => 'year',
        ]);

        $response->assertStatus(200)->assertJsonPath('type', 'upgraded');
        $this->assertCount(1, $fake->calls);
        $this->assertSame('updateSubscriptionPricing', $fake->calls[0]['method']);
        $this->assertSame('sub_existing', $fake->calls[0]['args'][0]);

        $subscription->refresh();
        $this->assertSame('year', $subscription->interval);
        $this->assertSame($creator->id, $subscription->plan_id);
        $this->assertNotNull($subscription->current_period_end);
    }
}
