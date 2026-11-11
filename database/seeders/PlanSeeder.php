<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the 4 Unwrap24 plans. Prices and limits live entirely in this
     * table (config-driven) - never hardcode pricing elsewhere.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'USD',
                'features' => [
                    '1 active calendar',
                    'Basic door themes',
                    'Unwrap24 branding on public page',
                    'Community support',
                ],
                'limits' => [
                    'max_calendars' => 1,
                    'max_days' => 12,
                    'max_team_members' => 1,
                    'custom_domain' => false,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Creator',
                'slug' => 'creator',
                'price_monthly' => 9,
                'price_yearly' => 90,
                'currency' => 'USD',
                'features' => [
                    'Up to 5 active calendars',
                    'Custom themes and branding removal',
                    'Email lead capture',
                    'Basic visitor analytics',
                    'Email support',
                ],
                'limits' => [
                    'max_calendars' => 5,
                    'max_days' => 31,
                    'max_team_members' => 1,
                    'custom_domain' => false,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price_monthly' => 29,
                'price_yearly' => 290,
                'currency' => 'USD',
                'features' => [
                    'Unlimited calendars',
                    'Team members & organizations',
                    'Custom domain support',
                    'Full analytics dashboard',
                    'Priority support',
                ],
                'limits' => [
                    'max_calendars' => null,
                    'max_days' => 31,
                    'max_team_members' => 10,
                    'custom_domain' => true,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'USD',
                'features' => [
                    'Everything in Business',
                    'Dedicated account manager',
                    'SLA & custom integrations',
                    'SSO',
                    'Custom contract pricing (contact sales)',
                ],
                'limits' => [
                    'max_calendars' => null,
                    'max_days' => null,
                    'max_team_members' => null,
                    'custom_domain' => true,
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
