<?php

namespace Tests\Feature\Calendars;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_draft_gets_a_null_response(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/calendars/draft')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_a_user_can_save_and_fetch_their_draft(): void
    {
        $user = User::factory()->create();
        $payload = ['details' => ['title' => 'My Countdown'], 'days' => []];

        $this->actingAs($user)->putJson('/api/calendars/draft', ['payload' => $payload, 'step' => 3])
            ->assertStatus(200)
            ->assertJsonPath('data.step', 3)
            ->assertJsonPath('data.payload.details.title', 'My Countdown');

        $this->actingAs($user)->getJson('/api/calendars/draft')
            ->assertStatus(200)
            ->assertJsonPath('data.step', 3)
            ->assertJsonPath('data.payload.details.title', 'My Countdown');

        $this->assertDatabaseCount('calendar_drafts', 1);
    }

    public function test_saving_a_draft_again_overwrites_it_in_place(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/calendars/draft', [
            'payload' => ['details' => ['title' => 'First draft']],
            'step' => 1,
        ])->assertStatus(200);

        $this->actingAs($user)->putJson('/api/calendars/draft', [
            'payload' => ['details' => ['title' => 'Second draft']],
            'step' => 4,
        ])->assertStatus(200)
            ->assertJsonPath('data.payload.details.title', 'Second draft')
            ->assertJsonPath('data.step', 4);

        $this->assertDatabaseCount('calendar_drafts', 1);
    }

    public function test_a_user_cannot_see_another_users_draft(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->putJson('/api/calendars/draft', [
            'payload' => ['details' => ['title' => "Owner's draft"]],
            'step' => 2,
        ])->assertStatus(200);

        $this->actingAs($other)->getJson('/api/calendars/draft')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_a_user_can_clear_their_draft(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/calendars/draft', [
            'payload' => ['details' => ['title' => 'Draft to delete']],
            'step' => 2,
        ])->assertStatus(200);

        $this->actingAs($user)->deleteJson('/api/calendars/draft')->assertStatus(200);

        $this->assertDatabaseCount('calendar_drafts', 0);

        $this->actingAs($user)->getJson('/api/calendars/draft')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_payload_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/calendars/draft', ['step' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payload');
    }
}
