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

    public function test_a_user_can_add_a_photo_to_their_own_bike(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", [
            'photo' => UploadedFile::fake()->image('bike.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('photo_url'));
        $this->assertCount(1, $response->json('photos'));
        Storage::disk('public')->assertExists($bike->fresh()->photos->first()->path);
    }

    public function test_a_bike_can_have_multiple_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('one.jpg')]);
        $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('two.jpg')]);
        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('three.jpg')]);

        $response->assertOk();
        $this->assertCount(3, $response->json('photos'));
        $this->assertSame(3, $bike->fresh()->photos()->count());
    }

    public function test_the_cover_photo_is_the_first_one_added(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('first.jpg')]);
        $firstUrl = $bike->fresh()->photo_url;

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('second.jpg')]);

        $this->assertSame($firstUrl, $response->json('photo_url'));
    }

    public function test_a_user_can_remove_a_bike_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $bike = Bike::factory()->for($user)->create();
        $add = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", ['photo' => UploadedFile::fake()->image('bike.jpg')]);
        $photoId = $add->json('photos.0.id');
        $path = $bike->fresh()->photos->first()->path;

        $response = $this->actingAs($user)->deleteJson("/api/bikes/{$bike->id}/photos/{$photoId}");

        $response->assertOk()->assertJsonCount(0, 'photos');
        $this->assertDatabaseMissing('bike_photos', ['id' => $photoId]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_user_cannot_add_a_photo_to_another_users_bike(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();

        $response = $this->actingAs($user)->postJson("/api/bikes/{$bike->id}/photos", [
            'photo' => UploadedFile::fake()->image('bike.jpg'),
        ]);

        $response->assertNotFound();
    }

    public function test_a_user_cannot_remove_a_photo_from_another_users_bike(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bike = Bike::factory()->for($other)->create();
        $photo = $bike->photos()->create(['path' => 'bike-photos/example.jpg']);

        $response = $this->actingAs($user)->deleteJson("/api/bikes/{$bike->id}/photos/{$photo->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('bike_photos', ['id' => $photo->id]);
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
