<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RideSpeedScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A straight "road" of 10 points 5 seconds apart, all at the same
     * coordinates the fake Overpass response will describe as a 50 km/h
     * road, so every track point matches it exactly (distance 0).
     */
    private function trackAtSpeed(float $speedKmh): array
    {
        $track = [];

        for ($i = 0; $i < 10; $i++) {
            $track[] = [
                'lat' => 43.5 + $i * 0.0001,
                'lng' => 5.4,
                'alt' => 100.0,
                'speed' => $speedKmh,
                't' => now()->addSeconds($i * 5)->toIso8601String(),
            ];
        }

        return $track;
    }

    private function fakeOverpassRoad(float $limitKmh): void
    {
        $geometry = [];
        for ($i = 0; $i < 10; $i++) {
            $geometry[] = ['lat' => 43.5 + $i * 0.0001, 'lon' => 5.4];
        }

        Http::fake([
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'way',
                        'id' => 1,
                        'tags' => ['highway' => 'primary', 'maxspeed' => (string) $limitKmh],
                        'geometry' => $geometry,
                    ],
                ],
            ]),
        ]);
    }

    private function payload(array $track): array
    {
        return [
            'title' => 'Speed Score Ride',
            'started_at' => now()->subHour()->toIso8601String(),
            'duration_seconds' => 45,
            'distance_meters' => 500,
            'avg_speed_kmh' => 60,
            'max_speed_kmh' => 90,
            'track' => $track,
        ];
    }

    public function test_riding_above_the_limit_lowers_the_score_and_records_an_event(): void
    {
        $this->fakeOverpassRoad(50);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(80)));

        $response->assertCreated();
        $score = $response->json('speed_score');
        $events = $response->json('speeding_events');

        $this->assertIsInt($score);
        $this->assertLessThan(100, $score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertCount(1, $events);
        $this->assertEquals(50.0, $events[0]['limit_kmh']);
        $this->assertEquals(80.0, $events[0]['max_speed_kmh']);
    }

    public function test_riding_within_the_tolerance_scores_a_perfect_100(): void
    {
        $this->fakeOverpassRoad(50);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(40)));

        $response->assertCreated();
        $response->assertJson(['speed_score' => 100, 'speeding_events' => []]);
    }

    public function test_a_road_with_no_explicit_maxspeed_falls_back_to_the_french_default_for_its_highway_type(): void
    {
        $geometry = [];
        for ($i = 0; $i < 10; $i++) {
            $geometry[] = ['lat' => 43.5 + $i * 0.0001, 'lon' => 5.4];
        }

        Http::fake([
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'way',
                        'id' => 1,
                        // No maxspeed tag - typical for a small rural road in OSM.
                        'tags' => ['highway' => 'unclassified'],
                        'geometry' => $geometry,
                    ],
                ],
            ]),
        ]);
        $user = User::factory()->create();

        // Rides 100 km/h on a road whose French statutory default is 80 km/h.
        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(100)));

        $response->assertCreated();
        $score = $response->json('speed_score');
        $events = $response->json('speeding_events');

        $this->assertIsInt($score);
        $this->assertLessThan(100, $score);
        $this->assertCount(1, $events);
        $this->assertEquals(80.0, $events[0]['limit_kmh']);
    }

    public function test_a_track_road_with_no_statutory_default_is_left_unscored(): void
    {
        $geometry = [];
        for ($i = 0; $i < 10; $i++) {
            $geometry[] = ['lat' => 43.5 + $i * 0.0001, 'lon' => 5.4];
        }

        Http::fake([
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'way',
                        'id' => 1,
                        'tags' => ['highway' => 'track'],
                        'geometry' => $geometry,
                    ],
                ],
            ]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(80)));

        $response->assertCreated();
        $response->assertJson(['speed_score' => null, 'speeding_events' => []]);
    }

    public function test_a_ride_with_no_matching_road_data_gets_no_score(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => []]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(80)));

        $response->assertCreated();
        $response->assertJson(['speed_score' => null, 'speeding_events' => []]);
    }

    public function test_an_overpass_failure_does_not_block_the_ride_upload(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response(null, 500),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(80)));

        $response->assertCreated();
        $response->assertJson(['speed_score' => null, 'speeding_events' => []]);
    }

    public function test_the_feed_exposes_the_speed_score(): void
    {
        $this->fakeOverpassRoad(50);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/rides', $this->payload($this->trackAtSpeed(80)));

        $response = $this->actingAs($user)->getJson('/api/rides');

        $response->assertOk();
        $this->assertIsInt($response->json('data.0.speed_score'));
    }
}
