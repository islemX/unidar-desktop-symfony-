<?php

namespace App\Service\AI;

use App\Entity\Listing;

/**
 * Feature 13: Total Cost of Living Calculator
 * Estimates true monthly cost: rent + transport + utilities + internet + groceries.
 */
class CostOfLivingService
{
    // Monthly cost estimates for Tunisia (TND) — 2024 averages
    private const COSTS = [
        'groceries_per_person'   => 280,
        'internet_basic'         => 45,
        'internet_fiber'         => 75,
        'public_transport_month' => 30,
        'taxi_per_trip'          => 5,
        'moto_taxi_month'        => 80,
        'gym'                    => 60,
        'dining_out_2x_week'     => 120,
    ];

    // Distance-based transport cost lookup (approx km to major universities)
    private const UNIVERSITIES = [
        'ESSECT'  => ['lat' => 36.8665, 'lng' => 10.1647],
        'ENIT'    => ['lat' => 36.8364, 'lng' => 10.0151],
        'ENSI'    => ['lat' => 36.7310, 'lng' => 10.2199],
        'FST'     => ['lat' => 36.7277, 'lng' => 10.2212],
        'ISET'    => ['lat' => 36.8488, 'lng' => 10.1947],
        'IHEC'    => ['lat' => 36.8598, 'lng' => 10.1893],
        'ESPRIT'  => ['lat' => 36.8961, 'lng' => 10.1879],
        'ISG'     => ['lat' => 36.8162, 'lng' => 10.1815],
    ];

    public function estimate(Listing $listing, array $options = []): array
    {
        $rent      = (float) ($listing->getPrice() ?? 0);
        $energy    = $this->estimateEnergy($listing);
        $transport = $this->estimateTransport($listing, $options['university'] ?? null);
        $internet  = $options['fiber'] ?? false ? self::COSTS['internet_fiber'] : self::COSTS['internet_basic'];
        $groceries = self::COSTS['groceries_per_person'] * (int)($options['occupants'] ?? 1);
        $extras    = $this->estimateExtras($options);

        $total = $rent + $energy + $transport + $internet + $groceries + $extras;

        return [
            'breakdown' => [
                'rent'      => (int) $rent,
                'utilities' => (int) $energy,
                'transport' => (int) $transport,
                'internet'  => (int) $internet,
                'groceries' => (int) $groceries,
                'extras'    => (int) $extras,
            ],
            'total_monthly' => (int) round($total),
            'total_annual'  => (int) round($total * 12),
            'rent_ratio'    => $total > 0 ? round($rent / $total * 100) : 0,
            'vs_median'     => $this->vsMedianComment($total),
            'tips'          => $this->savingsTips($rent, $transport, $groceries),
            'university_distance' => $this->universityDistance($listing, $options['university'] ?? null),
        ];
    }

    private function estimateEnergy(Listing $listing): float
    {
        $area = (float) ($listing->getArea() ?? 60);
        $base = $area * 0.85; // kWh/month
        if (in_array('air_conditioning', $listing->getAmenities() ?? [])) {
            $base += $area * 1.0;
        }
        return round($base * 0.19 + 25, 0); // electricity + water flat
    }

    private function estimateTransport(Listing $listing, ?string $university): float
    {
        if (!$university || !isset(self::UNIVERSITIES[$university])) {
            return self::COSTS['public_transport_month'];
        }

        $dist = $this->haversine(
            (float) ($listing->getLatitude() ?? 36.8),
            (float) ($listing->getLongitude() ?? 10.18),
            self::UNIVERSITIES[$university]['lat'],
            self::UNIVERSITIES[$university]['lng']
        );

        if ($dist < 1.5) return 0;   // Walking distance
        if ($dist < 5)   return 20;  // Short metro/bus
        if ($dist < 15)  return self::COSTS['public_transport_month'];
        return self::COSTS['moto_taxi_month']; // Far — taxi/louage
    }

    private function estimateExtras(array $options): float
    {
        $extras = 0;
        if ($options['gym'] ?? false)        $extras += self::COSTS['gym'];
        if ($options['dining_out'] ?? true)  $extras += self::COSTS['dining_out_2x_week'];
        return $extras;
    }

    private function universityDistance(Listing $listing, ?string $university): ?array
    {
        if (!$university || !isset(self::UNIVERSITIES[$university])) return null;

        $dist = $this->haversine(
            (float) ($listing->getLatitude() ?? 36.8),
            (float) ($listing->getLongitude() ?? 10.18),
            self::UNIVERSITIES[$university]['lat'],
            self::UNIVERSITIES[$university]['lng']
        );

        return [
            'university' => $university,
            'distance_km' => round($dist, 1),
            'mode'    => $dist < 1.5 ? 'walking' : ($dist < 5 ? 'public transport' : ($dist < 15 ? 'bus/metro' : 'taxi/louage')),
            'est_time_min' => (int) ($dist < 1.5 ? $dist * 12 : ($dist < 5 ? $dist * 5 + 10 : $dist * 3 + 15)),
        ];
    }

    private function vsMedianComment(float $total): string
    {
        // Median total cost of living for a Tunis student ≈ 780 TND/month
        $median = 780;
        $diff   = $total - $median;
        if (abs($diff) < 50) return 'Around the city median for student living costs';
        if ($diff > 0)       return sprintf('%.0f TND above the student median — consider cheaper alternatives', $diff);
        return sprintf('%.0f TND below the student median — excellent value', abs($diff));
    }

    private function savingsTips(float $rent, float $transport, float $groceries): array
    {
        $tips = [];
        if ($rent > 700)       $tips[] = 'Sharing with 1–2 roommates could cut rent by 30–50%';
        if ($transport > 60)   $tips[] = 'A monthly metro/bus pass (30 TND) is cheaper than daily tickets';
        if ($groceries > 350)  $tips[] = 'Cooking at home 5 days/week saves ~80 TND vs eating out';
        $tips[] = 'Student discounts available at most supermarkets — always carry your student ID';
        return $tips;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R   = 6371;
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
