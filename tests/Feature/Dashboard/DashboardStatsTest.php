<?php

namespace Tests\Feature\Dashboard;

use App\Models\AnalyticsEvent;
use App\Models\Calendar;
use App\Models\CalendarDay;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
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
                    'total_visits' => 0,
                    'unique_visitors' => 0,
                    'total_door_openings' => 0,
                    'total_shares' => 0,
                    'recent_activity' => [],
                ],
            ]);
    }

    public function test_stats_are_scoped_to_the_authenticated_users_calendars(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $calendar = Calendar::create([
            'user_id' => $user->id,
            'title' => 'My Calendar',
            'slug' => 'my-calendar',
            'calendar_type' => '7_day',
            'day_count' => 7,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-07',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $othersCalendar = Calendar::create([
            'user_id' => $otherUser->id,
            'title' => "Someone Else's Calendar",
            'slug' => 'someone-elses-calendar',
            'calendar_type' => '7_day',
            'day_count' => 7,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-07',
            'status' => 'draft',
            'visibility' => 'public',
        ]);

        $day = CalendarDay::create([
            'calendar_id' => $calendar->id,
            'day_number' => 1,
            'unlock_at' => '2026-12-01 00:00:00',
            'content_type' => 'rich_text',
            'status' => 'published',
        ]);

        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'visitor-a']);
        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'visitor-a']);
        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'visitor-b']);
        CalendarOpening::create(['calendar_id' => $calendar->id, 'calendar_day_id' => $day->id, 'visitor_hash' => 'visitor-a', 'opened_at' => now()]);
        AnalyticsEvent::create(['calendar_id' => $calendar->id, 'event_type' => 'share', 'visitor_hash' => 'visitor-a']);

        // Belongs to the other user's calendar; must not leak into this user's stats.
        CalendarVisit::create(['calendar_id' => $othersCalendar->id, 'visitor_hash' => 'visitor-c']);

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_calendars' => 1,
                    'published_calendars' => 1,
                    'draft_calendars' => 0,
                    'total_visits' => 3,
                    'unique_visitors' => 2,
                    'total_door_openings' => 1,
                    'total_shares' => 1,
                    'recent_activity' => [],
                ],
            ]);
    }
}
