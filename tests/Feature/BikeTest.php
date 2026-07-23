<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_bike(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bikes', [
            'brand' => 'Ducati',
            'model' => 'Monster 937',
            'year' => 2023,
            'nickname' => 'Red Beast',
            'engine_cc' => 937,
        ]);

        $response->assertCreated()->assertJsonFragment(['brand' => 'Ducati', 'nickname' => 'Red Beast']);
        $this->assertDatabaseHas('bikes', ['user_id' => $user->id, 'brand' => 'Ducati']);
    }

    public function test_a_user_only_sees_their_own_bikes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Bike::factory()->for($user)->create(['brand' => 'Yamaha']);
        Bike::factory()->for($other)->create(['brand' => 'Honda']);

        $response = $this->actingAs($user)->getJson('/api/bikes');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['brand' => 'Yamaha']);
        $response->assertJsonMissing(['brand' => 'Honda']);
    }

    public function test_a_user_can_update_their_own_bike(): void
    {
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create(['nickname' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/bikes/{$bike->id}", [
            'nickname' => 'New Name',
        ]);

        $response->assertOk()->assertJsonFragment(['nickname' => 'New Name']);
    }

    public function test_a_user_cannot_update_another_users_bike(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();

        $response = $this->actingAs($user)->putJson("/api/bikes/{$bike->id}", [
            'nickname' => 'Hijacked',
        ]);

        $response->assertNotFound();
    }

    public function test_a_user_can_delete_their_own_bike(): void
    {
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/api/bikes/{$bike->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('bikes', ['id' => $bike->id]);
    }

    public function test_a_user_cannot_delete_another_users_bike(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();

        $response = $this->actingAs($user)->deleteJson("/api/bikes/{$bike->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('bikes', ['id' => $bike->id]);
    }

    public function test_a_user_can_attach_a_photo_to_their_own_bike(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photo", [
            'photo' => UploadedFile::fake()->image('bike.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('photo_url'));
        Storage::disk('public')->assertExists($bike->fresh()->photo_path);
    }

    public function test_replacing_a_bike_photo_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photo", [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $firstPath = $bike->fresh()->photo_path;

        $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photo", [
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ]);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($bike->fresh()->photo_path);
    }

    public function test_a_user_cannot_attach_a_photo_to_another_users_bike(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photo", [
            'photo' => UploadedFile::fake()->image('bike.jpg'),
        ]);

        $response->assertNotFound();
    }

    public function test_a_riders_first_bike_becomes_their_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bikes', ['brand' => 'Ducati', 'model' => 'Monster']);

        $response->assertCreated()->assertJson(['is_default' => true]);
    }

    public function test_a_second_bike_is_not_default_by_default(): void
    {
        $user = User::factory()->create();
        Bike::factory()->for($user)->create(['is_default' => true]);

        $response = $this->actingAs($user)->postJson('/api/bikes', ['brand' => 'Yamaha', 'model' => 'MT-07']);

        $response->assertCreated()->assertJson(['is_default' => false]);
    }

    public function test_a_user_can_switch_their_default_bike(): void
    {
        $user = User::factory()->create();
        $first = Bike::factory()->for($user)->create(['is_default' => true]);
        $second = Bike::factory()->for($user)->create(['is_default' => false]);

        $response = $this->actingAs($user)->postJson("/api/bikes/{$second->id}/default");

        $response->assertOk()->assertJson(['is_default' => true]);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_a_user_cannot_default_another_users_bike(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/default");

        $response->assertNotFound();
    }

    public function test_deleting_the_default_bike_promotes_another(): void
    {
        $user = User::factory()->create();
        $older = Bike::factory()->for($user)->create(['is_default' => true, 'created_at' => now()->subDay()]);
        $newer = Bike::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user)->deleteJson("/api/bikes/{$older->id}");

        $this->assertTrue($newer->fresh()->is_default);
    }

    public function test_deleting_the_only_bike_leaves_no_default(): void
    {
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create(['is_default' => true]);

        $response = $this->actingAs($user)->deleteJson("/api/bikes/{$bike->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('bikes', ['id' => $bike->id]);
    }
}
