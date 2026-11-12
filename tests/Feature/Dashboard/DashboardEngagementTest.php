<?php

namespace Tests\Feature\Dashboard;

use App\Models\Calendar;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_one_entry_per_day_for_the_requested_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/engagement?days=7');

        $response->assertStatus(200);
        $this->assertCount(7, $response->json('data'));
        $this->assertSame(
            ['date', 'visits', 'openings', 'subscribers'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_it_buckets_todays_activity_correctly_and_stays_scoped_to_the_user(): void
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
            'title' => "Someone Else's",
            'slug' => 'someone-elses',
            'calendar_type' => '7_day',
            'day_count' => 7,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-07',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $day = $calendar->days()->create([
            'day_number' => 1,
            'unlock_at' => '2026-12-01 00:00:00',
            'content_type' => 'rich_text',
            'status' => 'published',
        ]);

        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v1']);
        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v2']);
        CalendarOpening::create(['calendar_id' => $calendar->id, 'calendar_day_id' => $day->id, 'visitor_hash' => 'v1', 'opened_at' => now()]);
        Lead::create(['calendar_id' => $calendar->id, 'email' => 'fan@example.com']);

        // Other user's activity must never leak into this response.
        CalendarVisit::create(['calendar_id' => $othersCalendar->id, 'visitor_hash' => 'v3']);

        $response = $this->actingAs($user)->getJson('/api/dashboard/engagement?days=7');

        $today = collect($response->json('data'))->last();

        $this->assertSame(now()->toDateString(), $today['date']);
        $this->assertSame(2, $today['visits']);
        $this->assertSame(1, $today['openings']);
        $this->assertSame(1, $today['subscribers']);
    }
}
