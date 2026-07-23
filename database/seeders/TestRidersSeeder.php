<?php

namespace Database\Seeders;

use App\Models\Ride;
use App\Models\User;
use App\Services\PolylineSimplifier;
use App\Services\SpeedLimitScorer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a handful of fake riders with a couple of short rides each, for
 * manually testing search, follow, and ride-companion tagging against a
 * real deployment. Not wired into DatabaseSeeder - run explicitly:
 *
 *   php artisan db:seed --class=Database\\Seeders\\TestRidersSeeder --force
 *
 * Safe to re-run: existing test riders (matched by email) are left alone.
 */
class TestRidersSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * Roughly centered on Lyon, France - matches the app's default map
     * center, so seeded rides show up somewhere sensible.
     */
    private const BASE_LAT = 45.75;

    private const BASE_LNG = 4.85;

    public function run(): void
    {
        $riders = [
            ['name' => 'Marco Dubois', 'username' => 'marco_dubois'],
            ['name' => 'Sara Lopez', 'username' => 'sara_lopez'],
            ['name' => 'Tom Bernard', 'username' => 'tom_bernard'],
            ['name' => 'Julie Martin', 'username' => 'julie_martin'],
            ['name' => 'Karim Haddad', 'username' => 'karim_haddad'],
        ];

        $simplifier = new PolylineSimplifier;
        $scorer = new SpeedLimitScorer;

        foreach ($riders as $data) {
            $email = $data['username'].'@redl-test.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );

            if ($user->rides()->exists()) {
                $this->command?->line("Skipping {$data['username']} - already has rides.");

                continue;
            }

            $rideCount = random_int(1, 2);
            for ($i = 1; $i <= $rideCount; $i++) {
                $this->createRide($user, $i, $simplifier, $scorer);
            }

            $this->command?->info("Seeded {$data['username']} ({$email}) with {$rideCount} ride(s).");
        }
    }

    private function createRide(User $user, int $n, PolylineSimplifier $simplifier, SpeedLimitScorer $scorer): void
    {
        $titles = ['Balade du soir', 'Petit tour', 'Sortie rapide', 'Virée matinale', 'Boucle du dimanche'];

        $baseLat = self::BASE_LAT + (random_int(-80, 80) / 1000);
        $baseLng = self::BASE_LNG + (random_int(-80, 80) / 1000);
        $startedAt = now()->subDays(random_int(0, 10))->subHours(random_int(0, 12));

        $pointCount = random_int(6, 10);
        $speedKmh = random_int(25, 55);
        $track = [];

        for ($i = 0; $i < $pointCount; $i++) {
            $track[] = [
                'lat' => round($baseLat + $i * 0.0012, 6),
                'lng' => round($baseLng + $i * 0.0009, 6),
                'alt' => 200 + random_int(-15, 15),
                'speed' => $speedKmh + random_int(-5, 5),
                't' => $startedAt->copy()->addSeconds($i * 20)->toIso8601String(),
            ];
        }

        $distanceMeters = random_int(600, 2500);
        $durationSeconds = random_int(180, 600);
        $speeding = $scorer->score($track);

        $user->rides()->create([
            'title' => $titles[array_rand($titles)]." #{$n}",
            'description' => 'Trajet de test créé automatiquement.',
            'started_at' => $startedAt,
            'duration_seconds' => $durationSeconds,
            'distance_meters' => $distanceMeters,
            'avg_speed_kmh' => round($distanceMeters / 1000 / ($durationSeconds / 3600), 1),
            'max_speed_kmh' => $speedKmh + 8,
            'track' => $track,
            'polyline_simplified' => $simplifier->simplify($track),
            'speed_score' => $speeding['score'],
            'speeding_events' => $speeding['events'],
        ]);
    }
}
