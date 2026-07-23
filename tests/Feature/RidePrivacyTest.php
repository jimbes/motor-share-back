<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A ride is only shared with its owner, mutual-follow friends, and tagged
 * companions - never with the wider public. See Ride::isVisibleTo().
 */
class RidePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_view_their_own_ride(): void
    {
        $owner = User::factory()->create();
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->getJson("/api/rides/{$ride->id}");

        $response->assertOk();
    }

    public function test_a_stranger_cannot_view_a_private_ride(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($stranger)->getJson("/api/rides/{$ride->id}");

        $response->assertForbidden();
    }

    public function test_a_one_way_follower_cannot_view_the_ride(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($follower)->getJson("/api/rides/{$ride->id}");

        $response->assertForbidden();
    }

    public function test_a_mutual_friend_can_view_the_ride(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $owner->following()->attach($friend->id);
        $friend->following()->attach($owner->id);
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($friend)->getJson("/api/rides/{$ride->id}");

        $response->assertOk();
    }

    public function test_a_tagged_companion_can_view_the_ride_even_without_friendship(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create();
        $ride = Ride::factory()->for($owner)->create();
        $ride->participants()->attach($companion->id);

        $response = $this->actingAs($companion)->getJson("/api/rides/{$ride->id}");

        $response->assertOk();
    }
}
