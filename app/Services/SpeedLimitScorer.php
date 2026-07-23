<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scores a ride's GPS track against real-world speed limits pulled from
 * OpenStreetMap (via the Overpass API), producing a 0-100 "riding score"
 * (100 = never above the posted limit) plus the individual speeding
 * events that dragged the score down.
 *
 * Most minor French roads aren't explicitly tagged with `maxspeed` in
 * OSM, but the limit is still public knowledge - it's whatever the Code
 * de la Route sets by default for that road's category. So when a
 * matched way has no explicit `maxspeed` tag, its `highway` type is used
 * to look up the French statutory default (see DEFAULT_LIMITS_BY_HIGHWAY).
 * Only road types with no sensible statutory default (tracks, paths,
 * private service roads, ...) are left unscored.
 */
class SpeedLimitScorer
{
    /**
     * French statutory default speed limits (Code de la Route) by OSM
     * `highway` value, used when a road has no explicit `maxspeed` tag.
     * Rural defaults (80 km/h) apply since the 2018 reform; some
     * départements have since posted 90 km/h on specific roads, which
     * shows up as an explicit `maxspeed` tag and takes precedence over
     * this table.
     */
    private const DEFAULT_LIMITS_BY_HIGHWAY = [
        'motorway' => 130.0,
        'motorway_link' => 110.0,
        'trunk' => 110.0,
        'trunk_link' => 90.0,
        'primary' => 80.0,
        'primary_link' => 80.0,
        'secondary' => 80.0,
        'secondary_link' => 80.0,
        'tertiary' => 80.0,
        'tertiary_link' => 80.0,
        'unclassified' => 80.0,
        'residential' => 50.0,
        'living_street' => 20.0,
    ];

    /**
     * Margin above the posted limit before a point counts as speeding, to
     * absorb GPS speed noise (matches typical radar/insurer tolerances).
     */
    private const TOLERANCE_KMH = 5.0;

    /** A track point further than this from every fetched road is treated as unmatched. */
    private const MATCH_DISTANCE_METERS = 25.0;

    /** Score points deducted per second spent per km/h above (limit + tolerance). */
    private const PENALTY_PER_KMH_SECOND = 0.05;

    /** Track point gaps larger than this (paused recording, GPS jump) are ignored. */
    private const MAX_GAP_SECONDS = 30;

    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    /**
     * @param  array<int, array{lat?: mixed, lng?: mixed, speed?: mixed, t?: mixed}>  $track
     * @return array{score: int|null, events: array<int, array<string, mixed>>}
     */
    public function score(array $track): array
    {
        $points = $this->usablePoints($track);

        if (count($points) < 2) {
            return $this->noData();
        }

        $ways = $this->fetchSpeedLimitWays($points);

        if ($ways === null || $ways === []) {
            return $this->noData();
        }

        return $this->computeScore($points, $ways);
    }

    /**
     * @return array{score: null, events: array{}}
     */
    private function noData(): array
    {
        return ['score' => null, 'events' => []];
    }

    /**
     * @param  array<int, array<string, mixed>>  $track
     * @return array<int, array{lat: float, lng: float, speed: float, t: Carbon}>
     */
    private function usablePoints(array $track): array
    {
        $points = [];

        foreach ($track as $point) {
            if (! isset($point['lat'], $point['lng'], $point['speed'], $point['t'])) {
                continue;
            }

            try {
                $t = Carbon::parse($point['t']);
            } catch (Throwable) {
                continue;
            }

            $points[] = [
                'lat' => (float) $point['lat'],
                'lng' => (float) $point['lng'],
                'speed' => (float) $point['speed'],
                't' => $t,
            ];
        }

        return $points;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<int, array{limit: float, geometry: array<int, array{lat: float, lon: float}>}>|null
     */
    private function fetchSpeedLimitWays(array $points): ?array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        $buffer = 0.002; // roughly 200m, enough to catch the road the rider was on.

        $south = min($lats) - $buffer;
        $west = min($lngs) - $buffer;
        $north = max($lats) + $buffer;
        $east = max($lngs) + $buffer;

        $query = sprintf(
            '[out:json][timeout:10];way["highway"](%F,%F,%F,%F);out geom;',
            $south,
            $west,
            $north,
            $east,
        );

        try {
            $response = Http::timeout(8)->asForm()->post(self::OVERPASS_URL, ['data' => $query]);
        } catch (Throwable $e) {
            Log::warning('Overpass speed-limit lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Overpass speed-limit lookup returned an error', ['status' => $response->status()]);

            return null;
        }

        $ways = [];

        foreach ($response->json('elements', []) as $element) {
            $tags = $element['tags'] ?? [];
            $limitKmh = $this->parseMaxspeedKmh($tags['maxspeed'] ?? null)
                ?? self::DEFAULT_LIMITS_BY_HIGHWAY[$tags['highway'] ?? ''] ?? null;
            $geometry = $element['geometry'] ?? null;

            if ($limitKmh === null || ! is_array($geometry) || count($geometry) < 2) {
                continue;
            }

            $ways[] = ['limit' => $limitKmh, 'geometry' => $geometry];
        }

        return $ways;
    }

    private function parseMaxspeedKmh(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        if (preg_match('/^(\d+(?:\.\d+)?)\s*mph$/i', $raw, $matches)) {
            return round(((float) $matches[1]) * 1.60934, 1);
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            return (float) $raw;
        }

        // Non-numeric values ("FR:urban", "walk", "none", ...) aren't a usable limit for this POC.
        return null;
    }

    /**
     * @param  array<int, array{lat: float, lng: float, speed: float, t: Carbon}>  $points
     * @param  array<int, array{limit: float, geometry: array<int, array{lat: float, lon: float}>}>  $ways
     * @return array{score: int|null, events: array<int, array<string, mixed>>}
     */
    private function computeScore(array $points, array $ways): array
    {
        $events = [];
        $currentEvent = null;
        $totalPenalty = 0.0;
        $matchedAny = false;

        $flush = function () use (&$events, &$currentEvent) {
            if ($currentEvent !== null) {
                $events[] = $currentEvent;
                $currentEvent = null;
            }
        };

        for ($i = 0; $i < count($points) - 1; $i++) {
            $a = $points[$i];
            $b = $points[$i + 1];
            $dt = $a['t']->diffInSeconds($b['t']);

            if ($dt <= 0 || $dt > self::MAX_GAP_SECONDS) {
                $flush();

                continue;
            }

            $limitKmh = $this->nearestLimitKmh($a, $ways);

            if ($limitKmh === null) {
                $flush();

                continue;
            }

            $matchedAny = true;
            $excessKmh = $a['speed'] - ($limitKmh + self::TOLERANCE_KMH);

            if ($excessKmh <= 0) {
                $flush();

                continue;
            }

            $totalPenalty += $dt * $excessKmh * self::PENALTY_PER_KMH_SECOND;

            if ($currentEvent === null) {
                $currentEvent = [
                    'started_at' => $a['t']->toIso8601String(),
                    'limit_kmh' => $limitKmh,
                    'max_speed_kmh' => $a['speed'],
                    'duration_seconds' => 0,
                ];
            }

            $currentEvent['max_speed_kmh'] = max($currentEvent['max_speed_kmh'], $a['speed']);
            $currentEvent['duration_seconds'] += $dt;
        }

        $flush();

        if (! $matchedAny) {
            return $this->noData();
        }

        $events = array_map(fn (array $event) => [
            ...$event,
            'max_speed_kmh' => round($event['max_speed_kmh'], 1),
            'excess_kmh' => round($event['max_speed_kmh'] - $event['limit_kmh'], 1),
        ], $events);

        $score = (int) round(max(0, min(100, 100 - $totalPenalty)));

        return ['score' => $score, 'events' => $events];
    }

    /**
     * @param  array{lat: float, lng: float}  $point
     * @param  array<int, array{limit: float, geometry: array<int, array{lat: float, lon: float}>}>  $ways
     */
    private function nearestLimitKmh(array $point, array $ways): ?float
    {
        $best = null;
        $bestDistance = self::MATCH_DISTANCE_METERS;

        foreach ($ways as $way) {
            $geometry = $way['geometry'];

            for ($i = 0; $i < count($geometry) - 1; $i++) {
                $distance = $this->pointToSegmentDistanceMeters(
                    $point['lat'],
                    $point['lng'],
                    $geometry[$i]['lat'],
                    $geometry[$i]['lon'],
                    $geometry[$i + 1]['lat'],
                    $geometry[$i + 1]['lon'],
                );

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $way['limit'];
                }
            }
        }

        return $best;
    }

    private function pointToSegmentDistanceMeters(float $lat, float $lng, float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $metersPerDegreeLat = 111_320;
        $metersPerDegreeLng = 111_320 * cos(deg2rad($lat));

        $x = $lng * $metersPerDegreeLng;
        $y = $lat * $metersPerDegreeLat;
        $x1 = $lng1 * $metersPerDegreeLng;
        $y1 = $lat1 * $metersPerDegreeLat;
        $x2 = $lng2 * $metersPerDegreeLng;
        $y2 = $lat2 * $metersPerDegreeLat;

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt(($x - $x1) ** 2 + ($y - $y1) ** 2);
        }

        $t = (($x - $x1) * $dx + ($y - $y1) * $dy) / ($dx ** 2 + $dy ** 2);
        $t = max(0.0, min(1.0, $t));

        $closestX = $x1 + $t * $dx;
        $closestY = $y1 + $t * $dy;

        return sqrt(($x - $closestX) ** 2 + ($y - $closestY) ** 2);
    }
}
