<?php

namespace Tests\Feature\Organizations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_an_organization(): void
    {
        $this->postJson('/api/organizations', ['name' => 'Acme'])->assertStatus(401);
    }

    public function test_an_authenticated_user_can_create_an_organization_and_becomes_its_owner_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/organizations', ['name' => 'Acme Gifts']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acme Gifts')
            ->assertJsonPath('data.slug', 'acme-gifts');

        $this->assertDatabaseHas('organizations', ['name' => 'Acme Gifts', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('organization_members', ['user_id' => $user->id, 'role' => 'owner']);
    }

    public function test_it_disambiguates_a_duplicate_slug(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson('/api/organizations', ['name' => 'Acme Gifts'])->assertStatus(201);

        $response = $this->actingAs($second)->postJson('/api/organizations', ['name' => 'Acme Gifts']);

        $response->assertStatus(201)->assertJsonPath('data.slug', 'acme-gifts-2');
    }
}
