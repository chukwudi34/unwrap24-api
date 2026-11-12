<?php

namespace Tests\Feature\Calendars;

use App\Models\Calendar;
use App\Models\CalendarDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '24 Days of Christmas Surprises',
            'description' => 'A festive countdown.',
            'category' => 'Christmas & Holiday',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-24',
            'timezone' => 'UTC',
            'cover_image' => '/images/builder-cover-v2.png',
            'calendar_type' => '24',
            'custom_days' => 24,
            'theme_config' => [
                'theme' => 'christmas',
                'primary' => '#c81e2b',
                'accent' => '#f5b51b',
                'background' => '#071a3d',
                'font' => 'modern',
                'door_style' => 'gift',
                'layout' => 'classic',
                'snowfall' => true,
                'show_branding' => true,
                'brand_name' => 'Unwrap24',
            ],
            'days' => [
                ['day_number' => 1, 'title' => 'Welcome gift', 'type' => 'text', 'text' => 'Hello!'],
                ['day_number' => 2, 'title' => '', 'type' => 'text', 'text' => ''],
            ],
            'slug' => '24-days-of-christmas-surprises',
            'visibility' => 'public',
            'timing' => 'now',
            'allow_share' => true,
            'collect_emails' => true,
            'allow_comments' => false,
        ], $overrides);
    }

    public function test_an_authenticated_user_can_publish_a_calendar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/calendars', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', '24-days-of-christmas-surprises')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.day_count', 24);

        $this->assertDatabaseHas('calendars', [
            'slug' => '24-days-of-christmas-surprises',
            'user_id' => $user->id,
            'status' => 'published',
            'calendar_type' => '24_day',
        ]);

        $calendar = Calendar::where('slug', '24-days-of-christmas-surprises')->first();

        $this->assertSame(2, CalendarDay::where('calendar_id', $calendar->id)->count());
        $this->assertDatabaseHas('calendar_days', [
            'calendar_id' => $calendar->id,
            'day_number' => 1,
            'title' => 'Welcome gift',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('calendar_days', [
            'calendar_id' => $calendar->id,
            'day_number' => 2,
            'status' => 'empty',
        ]);
    }

    public function test_a_private_calendar_hashes_its_access_password(): void
    {
        $user = User::factory()->create();

        $payload = $this->validPayload([
            'slug' => 'private-calendar',
            'visibility' => 'private',
            'password' => 'secretpass',
        ]);

        $response = $this->actingAs($user)->postJson('/api/calendars', $payload);

        $response->assertStatus(201);

        $calendar = Calendar::where('slug', 'private-calendar')->first();
        $this->assertNotNull($calendar->access_password);
        $this->assertTrue(Hash::check('secretpass', $calendar->access_password));
    }

    public function test_it_rejects_a_calendar_with_no_door_content(): void
    {
        $user = User::factory()->create();

        $payload = $this->validPayload([
            'slug' => 'empty-calendar',
            'days' => [
                ['day_number' => 1, 'title' => '', 'type' => 'text'],
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/calendars', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('days');
    }

    public function test_slug_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/calendars', $this->validPayload())->assertStatus(201);

        $response = $this->actingAs($user)->postJson('/api/calendars', $this->validPayload());

        $response->assertStatus(422)->assertJsonValidationErrors('slug');
    }
}
