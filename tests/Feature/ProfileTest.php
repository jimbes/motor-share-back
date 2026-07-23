<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_set_their_username(): void
    {
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->patchJson('/api/me', ['username' => 'marco_rides']);

        $response->assertOk()->assertJson(['username' => 'marco_rides']);
        $this->assertSame('marco_rides', $user->fresh()->username);
    }

    public function test_a_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->patchJson('/api/me', ['username' => 'taken']);

        $response->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_a_username_must_be_alphanumeric(): void
    {
        $user = User::factory()->create(['username' => null]);

        $response = $this->actingAs($user)->patchJson('/api/me', ['username' => 'not a valid name!']);

        $response->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_a_user_can_update_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->patchJson('/api/me', ['name' => 'New Name']);

        $response->assertOk()->assertJson(['name' => 'New Name']);
    }

    public function test_a_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('avatar_url'));
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_replacing_an_avatar_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('first.jpg')]);
        $firstPath = $user->fresh()->avatar_path;

        $this->actingAs($user)->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('second.jpg')]);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }
}
