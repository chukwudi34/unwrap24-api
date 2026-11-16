<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_when_the_user_has_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/billing/subscription')
            ->assertStatus(200)
            ->assertJson(['data' => null]);
    }

    public function test_it_returns_the_users_own_active_subscription(): void
    {
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

        $this->actingAs($user)->getJson('/api/billing/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.plan.slug', 'creator')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_it_falls_back_to_an_owned_organizations_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Business', 'slug' => 'business', 'price_monthly' => 29]);
        $organization = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'owner_id' => $user->id]);

        Subscription::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'active',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_456',
        ]);

        $this->actingAs($user)->getJson('/api/billing/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.plan.slug', 'business');
    }

    public function test_a_canceled_subscription_does_not_count_as_active(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'interval' => 'month',
            'status' => 'canceled',
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_789',
        ]);

        $this->actingAs($user)->getJson('/api/billing/subscription')
            ->assertStatus(200)
            ->assertJson(['data' => null]);
    }
}
