<?php

namespace App\Service\AI;

use App\Entity\Listing;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Feature 19: Campus Commute Score
 * Real travel time to university using OSRM (free, no API key needed).
 */
class CommuteScoreService
{
    // Public OSRM demo server — replace with self-hosted for production
    private const OSRM_BASE = 'https://router.project-osrm.org';

    private const UNIVERSITIES = [
        'ESSECT'  => ['lat' => 36.8665, 'lng' => 10.1647, 'name' => 'ESSECT Tunis'],
        'ENIT'    => ['lat' => 36.8364, 'lng' => 10.0151, 'name' => 'ENIT'],
        'ENSI'    => ['lat' => 36.7310, 'lng' => 10.2199, 'name' => 'ENSI'],
        'FST'     => ['lat' => 36.7277, 'lng' => 10.2212, 'name' => 'FST Tunis'],
        'ISET'    => ['lat' => 36.8488, 'lng' => 10.1947, 'name' => 'ISET'],
        'IHEC'    => ['lat' => 36.8598, 'lng' => 10.1893, 'name' => 'IHEC Carthage'],
        'ESPRIT'  => ['lat' => 36.8961, 'lng' => 10.1879, 'name' => 'ESPRIT'],
        'ISG'     => ['lat' => 36.8162, 'lng' => 10.1815, 'name' => 'ISG Tunis'],
    ];

    public function __construct(private readonly HttpClientInterface $http) {}

    /**
     * Calculate commute from listing to all (or specific) universities.
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

        // Sort by walking/driving time
        uasort($results, fn($a, $b) => $a['foot_minutes'] <=> $b['foot_minutes']);

        return [
            'listing_coords' => ['lat' => $lat, 'lng' => $lng],
            'routes'         => $results,
            'nearest'        => array_key_first($results),
            'score'          => $this->overallScore($results),
        ];
    }

    private function computeRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $name): array
    {
        $distance = $this->haversineKm($fromLat, $fromLng, $toLat, $toLng);

        // Try OSRM for walking route
        $foot = $this->osrmRoute($fromLat, $fromLng, $toLat, $toLng, 'foot');
        $car  = $this->osrmRoute($fromLat, $fromLng, $toLat, $toLng, 'driving');

        $footMin = $foot ? (int) round($foot['duration'] / 60) : (int) round($distance * 12);
        $carMin  = $car  ? (int) round($car['duration']  / 60) : (int) round($distance * 3 + 5);

        // Estimate bus time (no real-time data, heuristic)
        $busMin  = $distance < 1.5 ? $footMin : (int) round($distance * 5 + 10);

        return [
            'university'    => $name,
            'distance_km'   => round($distance, 1),
            'foot_minutes'  => $footMin,
            'car_minutes'   => $carMin,
            'bus_minutes'   => $busMin,
            'recommended'   => $this->recommendMode($distance, $footMin),
            'score'         => $this->routeScore($footMin, $distance),
        ];
    }

    private function osrmRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $profile): ?array
    {
        try {
            $url  = self::OSRM_BASE . "/route/v1/{$profile}/{$fromLng},{$fromLat};{$toLng},{$toLat}?overview=false";
            $resp = $this->http->request('GET', $url, ['timeout' => 5]);
            $data = $resp->toArray();

            if ($data['code'] === 'Ok' && !empty($data['routes'])) {
                return [
                    'duration' => $data['routes'][0]['duration'],
                    'distance' => $data['routes'][0]['distance'],
                ];
            }
        } catch (\Throwable) {}
        return null;
    }

    private function recommendMode(float $km, int $footMin): string
    {
        if ($km <= 1.2)  return 'walking';
        if ($km <= 4.0)  return 'cycling';
        if ($km <= 12.0) return 'bus/metro';
        return 'louage/taxi';
    }

    private function routeScore(int $footMin, float $km): int
    {
        // Score: closer = higher (100 = walking distance, 0 = far away)
        if ($km < 0.8)  return 100;
        if ($km < 2.0)  return 85;
        if ($km < 5.0)  return 65;
        if ($km < 10.0) return 45;
        if ($km < 20.0) return 25;
        return 10;
    }

    private function overallScore(array $routes): int
    {
        if (empty($routes)) return 0;
        $scores = array_column($routes, 'score');
        return (int) round(max($scores)); // best reachable university
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R   = 6371;
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat/2)**2 + cos($lat1*$rad)*cos($lat2*$rad)*sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}
