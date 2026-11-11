<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_a_revoked_token_can_no_longer_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');
        $plainTextToken = $token->plainTextToken;

        // Simulate the token having already been revoked (e.g. by a prior
        // logout call) before this request is made, rather than chaining
        // two HTTP calls in one test - Laravel's auth guard caches the
        // resolved user for the lifetime of a single test's application
        // instance, so a second call in the same method would read a
        // stale cached user regardless of the token's DB state.
        $token->accessToken->delete();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/auth/user')
            ->assertStatus(401);
    }
}
