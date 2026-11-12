<?php

namespace Tests\Feature\Dashboard;

use App\Models\Calendar;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalendar(User $user, array $overrides = []): Calendar
    {
        return Calendar::create(array_merge([
            'user_id' => $user->id,
            'title' => 'A Calendar',
            'slug' => 'a-calendar-'.uniqid(),
            'calendar_type' => '7_day',
            'day_count' => 7,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-07',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));
    }

    public function test_it_lists_the_users_calendars_with_engagement_counts(): void
    {
        $user = User::factory()->create();
        $calendar = $this->makeCalendar($user, [
            'title' => '24 Days of Christmas',
            'slug' => '24-days-of-christmas',
            'calendar_type' => '24_day',
            'day_count' => 24,
        ]);

        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v1']);
        CalendarVisit::create(['calendar_id' => $calendar->id, 'visitor_hash' => 'v2']);
        CalendarOpening::create([
            'calendar_id' => $calendar->id,
            'calendar_day_id' => $calendar->days()->create([
                'day_number' => 1,
                'unlock_at' => '2026-12-01 00:00:00',
                'content_type' => 'rich_text',
                'status' => 'published',
            ])->id,
            'visitor_hash' => 'v1',
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/calendars');

        $response->assertStatus(200)->assertJson([
            'data' => [
                [
                    'slug' => '24-days-of-christmas',
                    'type_label' => '24 Days',
                    'status' => 'published',
                    'visits_count' => 2,
                    'door_openings_count' => 1,
                ],
            ],
        ]);
    }

    public function test_it_caps_results_to_the_requested_limit_and_excludes_other_users_calendars(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        foreach (range(1, 7) as $index) {
            $this->makeCalendar($user, ['title' => "Calendar {$index}", 'slug' => "calendar-{$index}"]);
        }
        $this->makeCalendar($otherUser, ['title' => "Someone Else's", 'slug' => 'someone-elses']);

        $response = $this->actingAs($user)->getJson('/api/dashboard/calendars?limit=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertTrue(
            collect($response->json('data'))->every(fn ($row) => $row['slug'] !== 'someone-elses'),
        );
    }
}
