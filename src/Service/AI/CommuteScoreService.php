<?php

namespace App\Service\AI;

use App\Entity\Listing;

/**
 * Feature 19: Campus Commute Score
 * Estimates travel time to Tunisian universities using pure Haversine geometry.
 * Zero external API calls — fully offline.
 */
class CommuteScoreService
{
    // Average speeds (km/h) by transport mode
    private const SPEED = [
        'walking'  => 4.5,
        'cycling'  => 14.0,
        'bus'      => 22.0,   // includes stops & waiting
        'louage'   => 40.0,
    ];

    // Typical waiting/transfer overhead per mode (minutes)
    private const OVERHEAD = [
        'walking'  => 0,
        'cycling'  => 2,
        'bus'      => 10,  // walk to stop + wait
        'louage'   => 15,
    ];

    private const UNIVERSITIES = [
        'ESSECT'  => ['lat' => 36.8665, 'lng' => 10.1647, 'name' => 'ESSECT Tunis'],
        'ENIT'    => ['lat' => 36.8364, 'lng' => 10.0151, 'name' => 'ENIT'],
        'ENSI'    => ['lat' => 36.7310, 'lng' => 10.2199, 'name' => 'ENSI'],
        'FST'     => ['lat' => 36.7277, 'lng' => 10.2212, 'name' => 'FST Tunis'],
        'ISET'    => ['lat' => 36.8488, 'lng' => 10.1947, 'name' => 'ISET Tunis'],
        'IHEC'    => ['lat' => 36.8598, 'lng' => 10.1893, 'name' => 'IHEC Carthage'],
        'ESPRIT'  => ['lat' => 36.8961, 'lng' => 10.1879, 'name' => 'ESPRIT'],
        'ISG'     => ['lat' => 36.8162, 'lng' => 10.1815, 'name' => 'ISG Tunis'],
        'ESSTHS'  => ['lat' => 35.8288, 'lng' => 10.6407, 'name' => 'ESSTHS Sousse'],
        'ISMA'    => ['lat' => 35.7643, 'lng' => 10.8113, 'name' => 'ISMA Monastir'],
        'FSS'     => ['lat' => 34.7474, 'lng' => 10.7596, 'name' => 'FS Sfax'],
    ];

    /**
     * Calculate commute estimates from listing to all (or one) universities.
     */
    public function calculate(Listing $listing, ?string $university = null): array
    {
        $lat = (float) ($listing->getLatitude()  ?? 36.8190);
        $lng = (float) ($listing->getLongitude() ?? 10.1658);

        $targets = $university && isset(self::UNIVERSITIES[$university])
            ? [$university => self::UNIVERSITIES[$university]]
            : self::UNIVERSITIES;

        $results = [];
        foreach ($targets as $key => $uni) {
            $results[$key] = $this->computeRoute($lat, $lng, $uni['lat'], $uni['lng'], $uni['name']);
        }

        // Sort nearest first
        uasort($results, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return [
            'listing_coords' => ['lat' => $lat, 'lng' => $lng],
            'routes'         => $results,
            'nearest'        => array_key_first($results),
            'score'          => $this->overallScore($results),
            'method'         => 'haversine_estimation',
        ];
    }

    private function computeRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $name): array
    {
        $distKm = $this->haversineKm($fromLat, $fromLng, $toLat, $toLng);

        // Road factor: straight-line × 1.3 approximates road distance in Tunis
        $roadKm = $distKm * 1.3;

        $mode     = $this->recommendMode($distKm);
        $speed    = self::SPEED[$mode];
        $overhead = self::OVERHEAD[$mode];

        $drivingMin = (int) round(($roadKm / self::SPEED['louage']) * 60 + self::OVERHEAD['louage']);
        $busMin     = (int) round(($roadKm / self::SPEED['bus'])    * 60 + self::OVERHEAD['bus']);
        $walkMin    = (int) round(($roadKm / self::SPEED['walking']) * 60);
        $bestMin    = (int) round(($roadKm / $speed) * 60 + $overhead);

        return [
            'university'    => $name,
            'distance_km'   => round($distKm, 1),
            'road_km'       => round($roadKm, 1),
            'walking_min'   => $walkMin,
            'bus_min'       => $busMin,
            'driving_min'   => $drivingMin,
            'best_min'      => $bestMin,
            'recommended'   => $mode,
            'score'         => $this->routeScore($distKm),
            'label'         => $this->commuteLabel($distKm),
        ];
    }

    private function recommendMode(float $km): string
    {
        if ($km <= 1.2)  return 'walking';
        if ($km <= 5.0)  return 'cycling';
        if ($km <= 15.0) return 'bus';
        return 'louage';
    }

    private function routeScore(float $km): int
    {
        return match (true) {
            $km < 0.8  => 100,
            $km < 2.0  => 88,
            $km < 4.0  => 72,
            $km < 8.0  => 55,
            $km < 15.0 => 38,
            $km < 25.0 => 22,
            default    => 10,
        };
    }

    private function commuteLabel(float $km): string
    {
        return match (true) {
            $km < 1.2  => '🚶 Walking distance',
            $km < 5.0  => '🚲 Cycling distance',
            $km < 15.0 => '🚌 Short bus ride',
            $km < 30.0 => '🚌 Long bus / louage',
            default    => '🚗 Far — consider transport costs',
        };
    }

    private function overallScore(array $routes): int
    {
        if (empty($routes)) return 0;
        $best = reset($routes); // already sorted nearest first
        return $best['score'];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R   = 6371.0;
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat / 2) ** 2
           + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
