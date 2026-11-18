<?php

namespace Tests\Feature\Calendars;

use App\Models\AnalyticsEvent;
use App\Models\Calendar;
use App\Models\CalendarVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCalendarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_only_sees_their_own_calendars_with_engagement_counts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $calendar = Calendar::create([
            'user_id' => $user->id,
            'title' => 'My Countdown',
            'slug' => 'my-countdown',
            'calendar_type' => '12_day',
            'day_count' => 12,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-12',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        Calendar::create([
            'user_id' => $otherUser->id,
            'title' => 'Not Mine',
            'slug' => 'not-mine',
            'calendar_type' => '7_day',
            'day_count' => 7,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-07',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v1']);
        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v2']);
        AnalyticsEvent::create(['calendar_id' => $calendar->id, 'event_type' => 'share']);
        AnalyticsEvent::create(['calendar_id' => $calendar->id, 'event_type' => 'share']);

        $response = $this->actingAs($user)->getJson('/api/calendars');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'my-countdown')
            ->assertJsonPath('data.0.visits_count', 2)
            ->assertJsonPath('data.0.door_openings_count', 0)
            ->assertJsonPath('data.0.participants_count', 0)
            ->assertJsonPath('data.0.shares_count', 2);
    }

    public function test_a_user_with_no_calendars_gets_an_empty_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/calendars')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }
}
