<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_active_plans_ordered_and_marks_which_are_purchasable(): void
    {
        Plan::create(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0, 'sort_order' => 1]);
        Plan::create(['name' => 'Creator', 'slug' => 'creator', 'price_monthly' => 9, 'price_yearly' => 90, 'sort_order' => 2]);
        Plan::create(['name' => 'Hidden', 'slug' => 'hidden', 'price_monthly' => 5, 'is_active' => false, 'sort_order' => 3]);

        $response = $this->getJson('/api/plans');

        $response->assertStatus(200);
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertSame(['free', 'creator'], $slugs);

        $response->assertJsonPath('data.0.purchasable', false);
        $response->assertJsonPath('data.1.purchasable', true);
        $response->assertJsonPath('data.1.price_monthly', 9);
    }
}
