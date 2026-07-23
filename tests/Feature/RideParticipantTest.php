<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ride_owner_can_tag_a_companion(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson("/api/rides/{$ride->id}/participants", [
            'username' => 'sara_moto',
        ]);

        $response->assertOk()->assertJsonFragment(['username' => 'sara_moto']);
        $this->assertDatabaseHas('ride_participants', ['ride_id' => $ride->id, 'user_id' => $companion->id]);
    }

    public function test_tagging_the_same_companion_twice_does_not_duplicate(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();

        $this->actingAs($owner)->postJson("/api/rides/{$ride->id}/participants", ['username' => 'sara_moto']);
        $this->actingAs($owner)->postJson("/api/rides/{$ride->id}/participants", ['username' => 'sara_moto']);

        $this->assertSame(1, $ride->participants()->count());
    }

    public function test_a_non_owner_cannot_tag_a_companion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($other)->postJson("/api/rides/{$ride->id}/participants", [
            'username' => 'sara_moto',
        ]);

        $response->assertNotFound();
    }

    public function test_the_ride_owner_cannot_tag_themselves(): void
    {
        $owner = User::factory()->create(['username' => 'owner_handle']);
        $ride = Ride::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson("/api/rides/{$ride->id}/participants", [
            'username' => 'owner_handle',
        ]);

        $response->assertStatus(422);
    }

    public function test_the_ride_owner_can_remove_a_tagged_companion(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();
        $ride->participants()->attach($companion->id);

        $response = $this->actingAs($owner)->deleteJson("/api/rides/{$ride->id}/participants/{$companion->id}");

        $response->assertOk()->assertJsonCount(0);
        $this->assertDatabaseMissing('ride_participants', ['ride_id' => $ride->id, 'user_id' => $companion->id]);
    }

    public function test_a_tagged_companion_sees_the_ride_in_their_own_profile_feed(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();
        $ride->participants()->attach($companion->id);

        // The companion isn't friends with the owner, but was tagged on the
        // ride, so it's still visible to them - including in their own feed.
        $response = $this->actingAs($companion)->getJson("/api/rides?user_id={$companion->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($ride->id, $ids);
    }

    public function test_a_stranger_does_not_see_a_ride_via_a_tagged_companions_profile(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $stranger = User::factory()->create();
        $ride = Ride::factory()->for($owner)->create();
        $ride->participants()->attach($companion->id);

        $response = $this->actingAs($stranger)->getJson("/api/rides?user_id={$companion->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($ride->id, $ids);
    }

    public function test_a_ride_detail_includes_its_participants(): void
    {
        $owner = User::factory()->create();
        $companion = User::factory()->create(['username' => 'sara_moto']);
        $ride = Ride::factory()->for($owner)->create();
        $ride->participants()->attach($companion->id);

        $response = $this->actingAs($owner)->getJson("/api/rides/{$ride->id}");

        $response->assertOk()->assertJsonFragment(['username' => 'sara_moto']);
    }
}
