<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_search_riders_by_username(): void
    {
        $me = User::factory()->create(['username' => 'me_rider']);
        User::factory()->create(['username' => 'marco_rides', 'name' => 'Marco']);
        User::factory()->create(['username' => 'sara_moto', 'name' => 'Sara']);

        $response = $this->actingAs($me)->getJson('/api/users/search?q=marco');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['username' => 'marco_rides']);
    }

    public function test_search_matches_display_name_too(): void
    {
        $me = User::factory()->create(['username' => 'me_rider']);
        User::factory()->create(['username' => 'xyz123', 'name' => 'Marco Rossi']);

        $response = $this->actingAs($me)->getJson('/api/users/search?q=Rossi');

        $response->assertOk()->assertJsonFragment(['username' => 'xyz123']);
    }

    public function test_search_excludes_riders_without_a_username(): void
    {
        $me = User::factory()->create(['username' => 'me_rider']);
        User::factory()->create(['username' => null, 'name' => 'Marco No Handle']);

        $response = $this->actingAs($me)->getJson('/api/users/search?q=Marco');

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_search_excludes_the_authenticated_user_themself(): void
    {
        $me = User::factory()->create(['username' => 'marco_self']);

        $response = $this->actingAs($me)->getJson('/api/users/search?q=marco');

        $response->assertOk()->assertJsonCount(0);
    }

    public function test_a_query_is_required(): void
    {
        $me = User::factory()->create();

        $response = $this->actingAs($me)->getJson('/api/users/search');

        $response->assertUnprocessable();
    }

    public function test_a_public_profile_includes_riding_stats(): void
    {
        $me = User::factory()->create();
        $rider = User::factory()->create(['username' => 'marco_rides', 'name' => 'Marco']);
        Ride::factory()->for($rider)->create(['distance_meters' => 10000]);
        Ride::factory()->for($rider)->create(['distance_meters' => 5000]);

        $response = $this->actingAs($me)->getJson('/api/users/marco_rides');

        $response->assertOk()->assertJson([
            'username' => 'marco_rides',
            'rides_count' => 2,
            'distance_meters' => 15000,
        ]);
    }

    public function test_an_unknown_username_returns_404(): void
    {
        $me = User::factory()->create();

        $response = $this->actingAs($me)->getJson('/api/users/nobody-here');

        $response->assertNotFound();
    }
}
