<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_to_the_user_endpoint_are_rejected(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }

    public function test_unauthenticated_requests_to_logout_are_rejected(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    public function test_unauthenticated_requests_to_dashboard_stats_are_rejected(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }
}
