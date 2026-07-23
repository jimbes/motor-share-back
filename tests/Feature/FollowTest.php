<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_follow_another_rider(): void
    {
        $me = User::factory()->create();
        $rider = User::factory()->create(['username' => 'sara_moto']);

        $response = $this->actingAs($me)->postJson('/api/users/sara_moto/follow');

        $response->assertOk()->assertJson(['is_following' => true]);
        $this->assertDatabaseHas('follows', ['follower_id' => $me->id, 'followed_id' => $rider->id]);
    }

    public function test_following_twice_does_not_duplicate(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['username' => 'sara_moto']);

        $this->actingAs($me)->postJson('/api/users/sara_moto/follow');
        $this->actingAs($me)->postJson('/api/users/sara_moto/follow');

        $this->assertSame(1, $me->following()->count());
    }

    public function test_a_user_cannot_follow_themselves(): void
    {
        $me = User::factory()->create(['username' => 'me_rider']);

        $response = $this->actingAs($me)->postJson('/api/users/me_rider/follow');

        $response->assertStatus(422);
    }

    public function test_a_user_can_unfollow_a_rider(): void
    {
        $me = User::factory()->create();
        $rider = User::factory()->create(['username' => 'sara_moto']);
        $me->following()->attach($rider->id);

        $response = $this->actingAs($me)->deleteJson('/api/users/sara_moto/follow');

        $response->assertOk()->assertJson(['is_following' => false]);
        $this->assertDatabaseMissing('follows', ['follower_id' => $me->id, 'followed_id' => $rider->id]);
    }

    public function test_the_public_profile_reflects_follow_state(): void
    {
        $me = User::factory()->create();
        $rider = User::factory()->create(['username' => 'sara_moto']);
        $me->following()->attach($rider->id);

        $response = $this->actingAs($me)->getJson('/api/users/sara_moto');

        $response->assertOk()->assertJson(['is_following' => true, 'followers_count' => 1, 'is_friends' => false]);
    }

    public function test_the_public_profile_reflects_mutual_friend_state(): void
    {
        $me = User::factory()->create();
        $rider = User::factory()->create(['username' => 'sara_moto']);
        $me->following()->attach($rider->id);
        $rider->following()->attach($me->id);

        $response = $this->actingAs($me)->getJson('/api/users/sara_moto');

        $response->assertOk()->assertJson(['is_friends' => true]);
    }

    public function test_the_following_feed_scope_includes_self_and_mutual_friends(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $oneWayFollowed = User::factory()->create();
        $stranger = User::factory()->create();
        $me->following()->attach($friend->id);
        $friend->following()->attach($me->id); // follow-back makes them friends
        $me->following()->attach($oneWayFollowed->id); // not followed back - not a friend

        $myRide = Ride::factory()->for($me)->create();
        $friendRide = Ride::factory()->for($friend)->create();
        Ride::factory()->for($oneWayFollowed)->create();
        Ride::factory()->for($stranger)->create();

        $response = $this->actingAs($me)->getJson('/api/rides?scope=following');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$myRide->id, $friendRide->id], $ids->toArray());
    }

    public function test_the_default_feed_scope_only_shows_self_and_friends(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $me->following()->attach($friend->id);
        $friend->following()->attach($me->id);

        $friendRide = Ride::factory()->for($friend)->create();
        $strangerRide = Ride::factory()->for($stranger)->create();

        $response = $this->actingAs($me)->getJson('/api/rides');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($friendRide->id, $ids);
        $this->assertNotContains($strangerRide->id, $ids);
    }
}
