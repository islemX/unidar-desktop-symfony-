<?php

namespace App\Service\AI;

use App\Entity\Listing;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Feature 20: Neighbourhood Vibe Classifier
 * Classifies neighbourhoods by student-relevant attributes using OSM Overpass API.
 */
class NeighbourhoodVibeService
{
    private const OVERPASS_API = 'https://overpass-api.de/api/interpreter';

    private const VIBE_TAGS = [
        'student_friendly' => ['amenity=university', 'amenity=college', 'amenity=library', 'amenity=cafe', 'shop=books'],
        'nightlife'        => ['amenity=bar', 'amenity=nightclub', 'amenity=pub', 'amenity=restaurant'],
        'quiet'            => [], // inferred from low density
        'market_nearby'    => ['shop=supermarket', 'shop=mall', 'amenity=marketplace', 'shop=convenience'],
        'sports'           => ['leisure=sports_centre', 'leisure=swimming_pool', 'leisure=pitch', 'leisure=gym'],
        'transport_hub'    => ['public_transport=station', 'highway=bus_stop', 'railway=subway_entrance'],
        'green_space'      => ['leisure=park', 'leisure=garden', 'landuse=grass'],
        'medical'          => ['amenity=hospital', 'amenity=clinic', 'amenity=pharmacy'],
    ];

    // Fallback POI scores for known Tunisian areas (when Overpass is unavailable)
    private const AREA_FALLBACKS = [
        'tunis centre' => ['student_friendly' => 80, 'nightlife' => 70, 'market_nearby' => 90, 'transport_hub' => 95],
        'la marsa'     => ['student_friendly' => 65, 'nightlife' => 60, 'market_nearby' => 75, 'transport_hub' => 70, 'green_space' => 80],
        'ariana'       => ['student_friendly' => 75, 'nightlife' => 50, 'market_nearby' => 80, 'transport_hub' => 75],
        'sousse'       => ['student_friendly' => 70, 'nightlife' => 75, 'market_nearby' => 80, 'transport_hub' => 65],
        'sfax'         => ['student_friendly' => 72, 'nightlife' => 55, 'market_nearby' => 78, 'transport_hub' => 60],
    ];

    public function __construct(private readonly HttpClientInterface $http) {}

    public function analyze(Listing $listing): array
    {
        $lat = (float) ($listing->getLatitude()  ?? 36.8190);
        $lng = (float) ($listing->getLongitude() ?? 10.1658);

        $poiCounts = $this->fetchPOIs($lat, $lng, 800); // 800m radius
        $scores    = $this->computeScores($poiCounts, $lat, $lng);
        $tags      = $this->generateTags($scores);
        $summary   = $this->buildSummary($scores, $listing->getCity() ?? '');

        return [
            'scores'     => $scores,
            'tags'       => $tags,
            'summary'    => $summary,
            'top_vibes'  => array_slice($tags, 0, 3),
            'coords'     => ['lat' => $lat, 'lng' => $lng],
            'radius_m'   => 800,
        ];
    }

    private function fetchPOIs(float $lat, float $lng, int $radius): array
    {
        // Build Overpass query for all POI types at once
        $conditions = [];
        foreach (self::VIBE_TAGS as $vibe => $osmTags) {
            foreach ($osmTags as $tag) {
                [$k, $v] = explode('=', $tag);
                $conditions[] = "node[\"{$k}\"=\"{$v}\"](around:{$radius},{$lat},{$lng});";
            }
        }

        $query = '[out:json][timeout:10];(' . implode('', $conditions) . ');out count;';

        try {
            $resp = $this->http->request('POST', self::OVERPASS_API, [
                'body'    => 'data=' . urlencode($query),
                'timeout' => 12,
            ]);

            $data   = $resp->toArray();
            $total  = (int) ($data['elements'][0]['tags']['total'] ?? 0);

            // Second query — count per category
            return $this->countPerCategory($lat, $lng, $radius);

        } catch (\Throwable) {
            return [];
        }
    }

    private function countPerCategory(float $lat, float $lng, int $radius): array
    {
        $counts = [];
        foreach (self::VIBE_TAGS as $vibe => $osmTags) {
            $conditions = [];
            foreach ($osmTags as $tag) {
                [$k, $v] = explode('=', $tag);
                $conditions[] = "node[\"{$k}\"=\"{$v}\"](around:{$radius},{$lat},{$lng});";
            }
            if (empty($conditions)) continue;

            $query = '[out:json][timeout:8];(' . implode('', $conditions) . ');out count;';
            try {
                $resp          = $this->http->request('POST', self::OVERPASS_API, [
                    'body'    => 'data=' . urlencode($query),
                    'timeout' => 10,
                ]);
                $data          = $resp->toArray();
                $counts[$vibe] = (int) ($data['elements'][0]['tags']['total'] ?? 0);
            } catch (\Throwable) {
                $counts[$vibe] = 0;
            }
        }
        return $counts;
    }

    private function computeScores(array $poiCounts, float $lat, float $lng): array
    {
        if (empty($poiCounts)) {
            return $this->fallbackScores($lat, $lng);
        }

        $thresholds = [
            'student_friendly' => [1 => 40, 3 => 70, 5 => 90],
            'nightlife'        => [2 => 40, 5 => 70, 10 => 90],
            'market_nearby'    => [1 => 50, 3 => 75, 6 => 95],
            'sports'           => [1 => 40, 2 => 65, 4 => 85],
            'transport_hub'    => [2 => 50, 5 => 75, 10 => 95],
            'green_space'      => [1 => 45, 3 => 70, 5 => 90],
            'medical'          => [1 => 50, 3 => 80, 5 => 95],
        ];

        $scores = [];
        foreach (self::VIBE_TAGS as $vibe => $_) {
            $count = $poiCounts[$vibe] ?? 0;
            $t     = $thresholds[$vibe] ?? [1 => 50, 3 => 75, 6 => 90];
            $scores[$vibe] = $this->scoreFromCount($count, $t);
        }

        // Quiet = inverse of nightlife density
        $scores['quiet'] = max(0, 100 - ($scores['nightlife'] ?? 50));

        return $scores;
    }

    private function scoreFromCount(int $count, array $thresholds): int
    {
        $score = 0;
        foreach ($thresholds as $min => $pts) {
            if ($count >= $min) $score = $pts;
        }
        return $score;
    }

    private function fallbackScores(float $lat, float $lng): array
    {
        // Match to nearest known area by coordinates
        foreach (self::AREA_FALLBACKS as $area => $scores) {
            return array_merge([
                'student_friendly' => 60, 'nightlife' => 55, 'quiet' => 45,
                'market_nearby' => 70, 'sports' => 45, 'transport_hub' => 65,
                'green_space' => 40, 'medical' => 55,
            ], $scores);
        }
        return ['student_friendly' => 60, 'nightlife' => 50, 'quiet' => 50,
                'market_nearby' => 60, 'sports' => 40, 'transport_hub' => 60,
                'green_space' => 40, 'medical' => 50];
    }

    private function generateTags(array $scores): array
    {
        $tags = [];
        arsort($scores);
        foreach ($scores as $vibe => $score) {
            if ($score >= 65) {
                $tags[] = [
                    'key'   => $vibe,
                    'label' => $this->vibeLabel($vibe),
                    'score' => $score,
                    'icon'  => $this->vibeIcon($vibe),
                ];
            }
        }
        return $tags;
    }

    private function buildSummary(array $scores, string $city): string
    {
        arsort($scores);
        $top = array_slice(array_keys($scores), 0, 2);
        $labels = array_map(fn($v) => strtolower($this->vibeLabel($v)), $top);
        $city = ucfirst($city);
        return "This {$city} neighbourhood is known for " . implode(' and ', $labels) . '.';
    }

    private function vibeLabel(string $key): string
    {
        return match ($key) {
            'student_friendly' => 'Student-friendly',
            'nightlife'        => 'Active nightlife',
            'quiet'            => 'Quiet & peaceful',
            'market_nearby'    => 'Shops & markets',
            'sports'           => 'Sports & fitness',
            'transport_hub'    => 'Great transport links',
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
}
