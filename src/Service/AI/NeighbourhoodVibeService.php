<?php

namespace App\Service\AI;

use App\Entity\Listing;

/**
 * Feature 20: Neighbourhood Vibe Classifier
 * Scores neighbourhoods by student-relevant attributes using coordinate-based
 * proximity to known POI clusters. Fully offline — zero external API calls.
 */
class NeighbourhoodVibeService
{
    /**
     * Known POI clusters by category with (lat, lng, strength 1–3).
     * Strength 1 = small venue, 2 = medium, 3 = major hub.
     * Covers all major Tunisian university cities.
     */
    private const POI_CLUSTERS = [
        'student_friendly' => [
            // Tunis
            [36.8665, 10.1647, 3, 'ESSECT Tunis'],
            [36.8364, 10.0151, 3, 'ENIT Tunis'],
            [36.7310, 10.2199, 3, 'ENSI Tunis'],
            [36.8598, 10.1893, 2, 'IHEC Carthage'],
            [36.8961, 10.1879, 2, 'ESPRIT Ariana'],
            [36.8162, 10.1815, 2, 'ISG Tunis'],
            [36.8050, 10.1800, 3, 'Cité univ El Manar'],
            // Sfax
            [34.7403, 10.7600, 3, 'Faculté des Sciences Sfax'],
            [34.7550, 10.7200, 2, 'ISIMS Sfax'],
            [34.7300, 10.7650, 2, 'Cité univ Sfax'],
            // Sousse
            [35.8319, 10.6350, 3, 'Université de Sousse'],
            [35.8200, 10.6200, 2, 'ISSATS Sousse'],
            // Monastir
            [35.7643, 10.8113, 3, 'Faculté de Médecine Monastir'],
            [35.7700, 10.8000, 2, 'Cité univ Monastir'],
            // Gafsa
            [34.4311, 8.7757, 3, 'Université de Gafsa'],
            [34.4250, 8.7800, 2, 'ISEG Gafsa'],
            [34.4290, 8.7850, 2, 'Cité univ Gafsa'],
            // Kairouan
            [35.6781, 10.0963, 2, 'Université de Kairouan'],
            [35.6800, 10.1000, 2, 'Cité univ Kairouan'],
            // Bizerte
            [37.2740, 9.8739, 2, 'Université de Bizerte'],
            // Nabeul
            [36.4561, 10.7376, 2, 'Université de Nabeul'],
            // Gabès
            [33.8814, 10.0982, 2, 'Université de Gabès'],
            // Jendouba
            [36.5012, 8.7804, 2, 'Université de Jendouba'],
            // Kasserine
            [35.1676, 8.8365, 2, 'Institut Kasserine'],
        ],
        'nightlife' => [
            [36.8050, 10.1725, 3, 'Avenue Bourguiba bars'],
            [36.8620, 10.2350, 2, 'La Marsa restaurants'],
            [36.8710, 10.3200, 2, 'Gammarth nightlife'],
            [36.8900, 10.1900, 2, 'Ariana restaurants'],
            [35.8380, 10.6020, 2, 'Sousse medina cafés'],
            [34.7400, 10.7600, 2, 'Sfax centre restaurants'],
            [35.7643, 10.8113, 1, 'Monastir corniche'],
            [34.4311, 8.7757, 1, 'Gafsa centre cafés'],
            [35.6781, 10.0963, 1, 'Kairouan medina cafés'],
            [37.2740, 9.8739, 1, 'Bizerte port cafés'],
        ],
        'market_nearby' => [
            [36.7980, 10.1810, 3, 'Carrefour El Manar'],
            [36.8190, 10.1650, 3, 'Marché Central Tunis'],
            [36.8500, 10.2800, 2, 'Géant La Marsa'],
            [36.8920, 10.1870, 2, 'Géant Ariana'],
            [36.8100, 10.1750, 2, 'Monoprix Belvédère'],
            [35.8250, 10.6350, 2, 'Azur Sousse'],
            [34.7200, 10.7700, 2, 'Géant Sfax'],
            [34.7400, 10.7600, 2, 'Marché central Sfax'],
            [35.7643, 10.8113, 2, 'Marché Monastir'],
            [34.4311, 8.7757, 2, 'Marché Gafsa'],
            [35.6781, 10.0963, 2, 'Marché Kairouan'],
            [37.2740, 9.8739, 2, 'Marché Bizerte'],
            [36.4561, 10.7376, 2, 'Marché Nabeul'],
            [33.8814, 10.0982, 2, 'Marché Gabès'],
            [36.5012, 8.7804, 1, 'Marché Jendouba'],
            [35.1676, 8.8365, 1, 'Marché Kasserine'],
        ],
        'transport_hub' => [
            [36.8130, 10.1750, 3, 'Tunis Marine metro'],
            [36.8190, 10.1658, 3, 'Gare de Tunis'],
            [36.8000, 10.1800, 2, 'Bab Saadoun bus'],
            [36.7960, 10.1770, 3, 'Bab El Fellah metro'],
            [36.8450, 10.2550, 2, 'La Marsa TGM'],
            [35.8250, 10.6350, 2, 'Gare Sousse'],
            [34.7400, 10.7600, 3, 'Gare Sfax'],
            [35.7643, 10.8113, 2, 'Gare Monastir'],
            [34.4311, 8.7757, 2, 'Gare routière Gafsa'],
            [35.6781, 10.0963, 2, 'Gare Kairouan'],
            [37.2740, 9.8739, 2, 'Gare Bizerte'],
            [36.4561, 10.7376, 1, 'Louage Nabeul'],
            [33.8814, 10.0982, 2, 'Gare Gabès'],
            [36.5012, 8.7804, 1, 'Gare Jendouba'],
            [35.1676, 8.8365, 1, 'Gare Kasserine'],
        ],
        'green_space' => [
            [36.8190, 10.1660, 3, 'Parc Belvédère Tunis'],
            [36.8300, 10.1900, 2, 'Jardins Carthage'],
            [36.8640, 10.2350, 2, 'Parc La Marsa'],
            [36.7950, 10.1820, 1, 'Square El Manar'],
            [35.8380, 10.5950, 2, 'Parc Sousse'],
            [34.7400, 10.7600, 1, 'Parc Sfax'],
            [34.4311, 8.7757, 2, 'Parc Gafsa'],
            [35.7643, 10.8113, 2, 'Plage Monastir'],
            [37.2740, 9.8739, 2, 'Plage Bizerte'],
            [36.4561, 10.7376, 2, 'Plage Nabeul'],
            [33.8814, 10.0982, 1, 'Plage Gabès'],
        ],
        'sports' => [
            [36.8220, 10.2200, 3, 'Stade El Menzah'],
            [36.7800, 10.1700, 2, 'Salle sport El Manar'],
            [36.8600, 10.2300, 2, 'Club La Marsa'],
            [35.8350, 10.6100, 2, 'Stade Sousse'],
            [34.7400, 10.7600, 2, 'Stade Sfax'],
            [35.7643, 10.8113, 2, 'Stade Monastir'],
            [34.4311, 8.7757, 1, 'Stade Gafsa'],
            [35.6781, 10.0963, 1, 'Stade Kairouan'],
            [37.2740, 9.8739, 1, 'Stade Bizerte'],
        ],
        'medical' => [
            [36.8170, 10.1820, 3, 'Hôpital La Rabta'],
            [36.7990, 10.1760, 3, 'Hôpital Charles Nicolle'],
            [36.8280, 10.1670, 2, 'Clinique Oliviers'],
            [36.8700, 10.1750, 2, 'Hôpital Ariana'],
            [35.8200, 10.6300, 2, 'CHU Sahloul Sousse'],
            [34.7400, 10.7500, 2, 'CHU Hédi Chaker Sfax'],
            [35.7643, 10.8113, 2, 'CHU Monastir'],
            [34.4311, 8.7757, 2, 'Hôpital régional Gafsa'],
            [35.6781, 10.0963, 2, 'CHU Kairouan'],
            [37.2740, 9.8739, 2, 'Hôpital Bizerte'],
            [36.4561, 10.7376, 1, 'Hôpital Nabeul'],
            [33.8814, 10.0982, 2, 'CHU Gabès'],
            [36.5012, 8.7804, 1, 'Hôpital Jendouba'],
            [35.1676, 8.8365, 1, 'Hôpital Kasserine'],
        ],
    ];

    // Radius in km within which a POI contributes to a neighbourhood's score
    private const INFLUENCE_RADIUS_KM = 2.5;

    public function analyze(Listing $listing): array
    {
        $lat = (float) ($listing->getLatitude()  ?? 36.8190);
        $lng = (float) ($listing->getLongitude() ?? 10.1658);

        $scores  = $this->computeScores($lat, $lng);
        $tags    = $this->generateTags($scores);
        $summary = $this->buildSummary($scores, $listing->getCity() ?? 'Tunis');

        return [
            'scores'     => $scores,
            'tags'       => $tags,
            'summary'    => $summary,
            'top_vibes'  => array_slice($tags, 0, 3),
            'coords'     => ['lat' => $lat, 'lng' => $lng],
            'method'     => 'coordinate_proximity',
        ];
    }

    private function computeScores(float $lat, float $lng): array
    {
        $scores = [];

        foreach (self::POI_CLUSTERS as $vibe => $pois) {
            $total = 0.0;

            foreach ($pois as [$poiLat, $poiLng, $strength]) {
                $dist = $this->haversineKm($lat, $lng, $poiLat, $poiLng);

                if ($dist <= self::INFLUENCE_RADIUS_KM) {
                    // Inverse distance weighting × strength
                    $influence = ($strength * (1.0 - $dist / self::INFLUENCE_RADIUS_KM));
                    $total    += $influence;
                }
            }

            // Normalise: max possible score if standing right on top of a strength-3 POI = 3.0
            $scores[$vibe] = min(100, (int) round($total / 3.0 * 100));
        }

        // Quiet = inverse of nightlife + transport density
        $scores['quiet'] = max(0, 100 - (int)(($scores['nightlife'] + $scores['transport_hub']) / 2.5));

        return $scores;
    }

    private function generateTags(array $scores): array
    {
        $tags = [];
        arsort($scores);

        foreach ($scores as $vibe => $score) {
            if ($score >= 20) {  // Only show if meaningfully present
                $tags[] = [
                    'key'   => $vibe,
                    'label' => $this->vibeLabel($vibe),
                    'score' => $score,
                    'icon'  => $this->vibeIcon($vibe),
                    'bar'   => min(100, $score),
                ];
            }
        }

        return $tags;
    }

    private function buildSummary(array $scores, string $city): string
    {
        arsort($scores);
        $top = array_slice(array_keys($scores), 0, 2);

        if (empty($top)) {
            return "A {$city} neighbourhood with mixed amenities.";
        }

        $labels = array_map(fn($v) => strtolower($this->vibeLabel($v)), $top);
        return ucfirst($city) . ' neighbourhood strong on ' . implode(' and ', $labels) . '.';
    }

    private function vibeLabel(string $key): string
    {
        return match ($key) {
            'student_friendly' => 'Student-friendly',
            'nightlife'        => 'Dining & nightlife',
            'quiet'            => 'Quiet & residential',
            'market_nearby'    => 'Shops & markets',
            'sports'           => 'Sports & fitness',
            'transport_hub'    => 'Transport links',
            'green_space'      => 'Parks & green areas',
            'medical'          => 'Healthcare access',
            default            => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    private function vibeIcon(string $key): string
    {
        return match ($key) {
            'student_friendly' => '🎓',
            'nightlife'        => '🎵',
            'quiet'            => '🌙',
            'market_nearby'    => '🛒',
            'sports'           => '⚽',
            'transport_hub'    => '🚌',
            'green_space'      => '🌳',
            'medical'          => '🏥',
            default            => '📍',
        };
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
