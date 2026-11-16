<?php

namespace App\Actions\Billing;

use App\Billing\CheckoutOutcome;
use App\Billing\CheckoutSessionData;
use App\Billing\PaymentGateway;
use App\Billing\PaymentGatewayFactory;
use App\Billing\PlanPricing;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateCheckoutSessionAction
{
    public function __construct(private readonly PaymentGatewayFactory $gateways) {}

    /**
     * @param  array{plan_slug: string, interval: string, organization_id?: string|null}  $data
     */
    public function handle(User $user, array $data): CheckoutOutcome
    {
        $plan = Plan::where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();
        $interval = $data['interval'] === 'year' ? 'year' : 'month';

        if (! $this->isPurchasable($plan)) {
            throw ValidationException::withMessages([
                'plan_slug' => 'This plan is not available for self-serve checkout.',
            ]);
        }

        $organization = $plan->slug === 'business'
            ? $this->resolveOrganization($user, $data['organization_id'] ?? null)
            : null;

        $gateway = $this->gateways->default();

        $existing = $organization ? $organization->activeSubscription() : $user->activeSubscription();

        if ($existing) {
            return $this->handleExistingSubscription($existing, $plan, $interval, $gateway);
        }

        return $this->startCheckout($user, $organization, $plan, $interval, $gateway);
    }

    private function startCheckout(User $user, ?Organization $organization, Plan $plan, string $interval, PaymentGateway $gateway): CheckoutOutcome
    {
        $pricing = $this->pricingFor($plan, $interval);

        $frontendUrl = rtrim((string) config('services.frontend_url'), '/');
        $successUrl = "{$frontendUrl}/billing/success?session_id={CHECKOUT_SESSION_ID}&plan={$plan->slug}&interval={$interval}";
        $cancelUrl = "{$frontendUrl}/checkout?plan={$plan->slug}&interval={$interval}"
            .($organization ? "&organization={$organization->ulid}" : '');

        $session = $gateway->createCheckoutSession(new CheckoutSessionData(
            customerEmail: $user->email,
            existingCustomerId: $user->stripe_customer_id,
            pricing: $pricing,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            clientReferenceId: (string) $user->id,
            metadata: array_filter([
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'organization_id' => $organization ? (string) $organization->id : null,
                'interval' => $interval,
            ]),
        ));

        return CheckoutOutcome::checkout((string) $session->url);
    }

    private function handleExistingSubscription(Subscription $existing, Plan $plan, string $interval, PaymentGateway $gateway): CheckoutOutcome
    {
        if ($existing->plan_id === $plan->id && $existing->interval === $interval) {
            return CheckoutOutcome::alreadySubscribed($existing);
        }

        if ($existing->provider !== $gateway->provider() || ! $existing->provider_subscription_id) {
            throw ValidationException::withMessages([
                'plan_slug' => 'Your current subscription cannot be changed automatically. Please contact support.',
            ]);
        }

        $pricing = $this->pricingFor($plan, $interval);
        $updated = $gateway->updateSubscriptionPricing($existing->provider_subscription_id, $pricing);

        $existing->update([
            'plan_id' => $plan->id,
            'interval' => $interval,
            'status' => $updated->status,
            'current_period_start' => $updated->currentPeriodStart,
            'current_period_end' => $updated->currentPeriodEnd,
            'cancel_at_period_end' => $updated->cancelAtPeriodEnd,
        ]);

        return CheckoutOutcome::upgraded($existing->fresh());
    }

    private function isPurchasable(Plan $plan): bool
    {
        return $plan->is_active && ((float) $plan->price_monthly > 0 || (float) $plan->price_yearly > 0);
    }

    private function resolveOrganization(User $user, ?string $organizationId): Organization
    {
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Choose or create an organization for the Business plan first.',
            ]);
        }

        $organization = Organization::where('ulid', $organizationId)->first();

        if (! $organization || $organization->owner_id !== $user->id) {
            throw ValidationException::withMessages([
                'organization_id' => 'That organization could not be found.',
            ]);
        }

        return $organization;
    }

    private function pricingFor(Plan $plan, string $interval): PlanPricing
    {
        $amount = $interval === 'year' ? $plan->price_yearly : $plan->price_monthly;

        return new PlanPricing(
            productName: "Unwrap24 {$plan->name} (billed {$interval}ly)",
            unitAmount: (int) round(((float) $amount) * 100),
            currency: strtolower((string) $plan->currency),
            interval: $interval,
        );
    }
}
