<?php

namespace Tests\Feature\Calendars;

use App\Models\Calendar;
use App\Models\CalendarDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalendar(User $user, array $overrides = [], array $days = []): Calendar
    {
        $calendar = Calendar::create(array_merge([
            'user_id' => $user->id,
            'title' => '24 Days of Christmas',
            'slug' => 'public-calendar-'.uniqid(),
            'description' => 'Open a door every day.',
            'calendar_type' => '24_day',
            'day_count' => 24,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-24',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));

        foreach ($days as $day) {
            CalendarDay::create(array_merge([
                'calendar_id' => $calendar->id,
                'unlock_at' => '2026-12-01 00:00:00',
                'content_type' => 'rich_text',
                'status' => 'published',
            ], $day));
        }

        return $calendar;
    }

    public function test_a_published_calendar_is_readable_by_slug(): void
    {
        $user = User::factory()->create(['name' => 'Ada Creator']);
        $calendar = $this->makeCalendar($user, ['slug' => 'christmas-countdown'], [
            ['day_number' => 1, 'title' => 'Welcome gift', 'body' => json_encode(['text' => 'Hello!'])],
            ['day_number' => 2, 'title' => 'Day two', 'body' => json_encode(['text' => 'Surprise!'])],
        ]);

        $response = $this->getJson('/api/public/calendars/christmas-countdown');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'christmas-countdown')
            ->assertJsonPath('data.title', '24 Days of Christmas')
            ->assertJsonPath('data.has_password', false)
            ->assertJsonPath('data.creator.name', 'Ada Creator')
            ->assertJsonPath('data.days.0.day_number', 1)
            ->assertJsonPath('data.days.0.title', 'Welcome gift')
            ->assertJsonPath('data.days.1.day_number', 2);

        $this->assertDatabaseMissing('calendar_visits', ['calendar_id' => $calendar->id]);
    }

    public function test_draft_archived_and_unknown_slugs_return_404(): void
    {
        $user = User::factory()->create();
        $this->makeCalendar($user, ['slug' => 'still-a-draft', 'status' => 'draft']);
        $this->makeCalendar($user, ['slug' => 'long-gone', 'status' => 'archived']);

        $this->getJson('/api/public/calendars/still-a-draft')->assertStatus(404);
        $this->getJson('/api/public/calendars/long-gone')->assertStatus(404);
        $this->getJson('/api/public/calendars/does-not-exist')->assertStatus(404);
    }

    public function test_an_unlisted_calendar_is_reachable_by_direct_link(): void
    {
        $user = User::factory()->create();
        $this->makeCalendar($user, ['slug' => 'unlisted-surprise', 'visibility' => 'unlisted']);

        $this->getJson('/api/public/calendars/unlisted-surprise')
            ->assertStatus(200)
            ->assertJsonPath('data.visibility', 'unlisted');
    }

    public function test_a_private_calendar_hides_its_days_until_unlocked(): void
    {
        $user = User::factory()->create();
        $this->makeCalendar(
            $user,
            ['slug' => 'secret-calendar', 'visibility' => 'private', 'access_password' => Hash::make('open-sesame')],
            [['day_number' => 1, 'title' => 'Secret', 'body' => json_encode(['text' => 'Hidden'])]],
        );

        $this->getJson('/api/public/calendars/secret-calendar')
            ->assertStatus(200)
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.days', []);

        $this->postJson('/api/public/calendars/secret-calendar/unlock', ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'password' => 'That password does not match this calendar. Check your invitation and try again.',
            ]);

        $this->postJson('/api/public/calendars/secret-calendar/unlock', ['password' => 'open-sesame'])
            ->assertStatus(200)
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.days.0.title', 'Secret');
    }

    public function test_visits_opens_and_shares_are_recorded(): void
    {
        $user = User::factory()->create();
        $calendar = $this->makeCalendar($user, ['slug' => 'tracked-calendar'], [
            ['day_number' => 1, 'title' => 'One', 'unlock_at' => '2020-01-01 00:00:00'],
        ]);

        $this->postJson('/api/public/calendars/tracked-calendar/visit')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->postJson('/api/public/calendars/tracked-calendar/open', ['day_number' => 1])
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->postJson('/api/public/calendars/tracked-calendar/share')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('calendar_visits', 1);
        $this->assertDatabaseCount('calendar_openings', 1);
        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseHas('analytics_events', [
            'calendar_id' => $calendar->id,
            'event_type' => 'share',
        ]);
    }

    public function test_a_day_that_has_not_unlocked_yet_cannot_be_recorded(): void
    {
        $user = User::factory()->create();
        $this->makeCalendar($user, ['slug' => 'future-doors'], [
            ['day_number' => 24, 'title' => 'Last day', 'unlock_at' => '2099-12-24 00:00:00'],
        ]);

        $this->postJson('/api/public/calendars/future-doors/open', ['day_number' => 24])
            ->assertStatus(403);

        $this->postJson('/api/public/calendars/future-doors/open', ['day_number' => 99])
            ->assertStatus(422);

        $this->assertDatabaseCount('calendar_openings', 0);
    }

    public function test_analytics_endpoints_do_not_leak_draft_calendars(): void
    {
        $user = User::factory()->create();
        $this->makeCalendar($user, ['slug' => 'draft-analytics', 'status' => 'draft']);

        $this->postJson('/api/public/calendars/draft-analytics/visit')->assertStatus(404);
        $this->postJson('/api/public/calendars/draft-analytics/share')->assertStatus(404);

        $this->assertDatabaseCount('calendar_visits', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }
}
