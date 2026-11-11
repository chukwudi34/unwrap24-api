<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_fetch_dashboard_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_calendars' => 0,
                    'published_calendars' => 0,
                    'draft_calendars' => 0,
                    'total_visitors' => 0,
                    'total_door_openings' => 0,
                    'recent_activity' => [],
                ],
            ]);
    }
}
