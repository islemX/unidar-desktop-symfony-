<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Repository\ListingRepository;

/**
 * Feature 1: Natural Language Search
 * "Quiet studio near ESSEC, WiFi included, under 800 TND"
 * → semantic + rule-based hybrid ranking over active listings.
 */
class NaturalLanguageSearchService
{
    public function __construct(
        private readonly ListingRepository $listingRepository,
        private readonly EmbeddingService  $embedding,
    ) {}

    /**
     * @return array  ['listings' => Listing[], 'parsed' => array, 'scores' => array]
     */
    public function search(string $query, int $limit = 20): array
    {
        $parsed   = $this->parseIntent($query);
        $listings = $this->listingRepository->findBy(['status' => 'active'], null, 200);

        if (empty($listings)) {
            return ['listings' => [], 'parsed' => $parsed, 'scores' => []];
        }

        $queryEmb = $this->embedding->embed($query);
        $scores   = [];

        foreach ($listings as $listing) {
            $score = 0.0;

            // ── Semantic score (embedding similarity) ──
            $listingText = $this->buildListingText($listing);
            $listingEmb  = $this->embedding->embed($listingText);
            $score      += $this->embedding->similarity($queryEmb, $listingEmb) * 50;

            // ── Rule-based boosts ──
            if ($parsed['max_price'] && $listing->getPrice() <= $parsed['max_price']) {
                $score += 20;
            }
            if ($parsed['bedrooms'] && $listing->getBedrooms() == $parsed['bedrooms']) {
                $score += 15;
            }
            if ($parsed['property_type'] && str_contains(
                strtolower($listing->getPropertyType() ?? ''),
                $parsed['property_type']
            )) {
                $score += 10;
            }
            $listingAmenities = method_exists($listing, 'getAmenities') ? ($listing->getAmenities() ?? []) : [];
            foreach ($parsed['amenities'] as $amenity) {
                if (in_array($amenity, $listingAmenities)) {
                    $score += 5;
                }
            }
            if ($parsed['city'] && str_contains(
                strtolower($listing->getCity() ?? ''),
                strtolower($parsed['city'])
            )) {
                $score += 25;
            }

            $scores[$listing->getId()] = $score;
        }

        arsort($scores);
        $topIds   = array_slice(array_keys($scores), 0, $limit, true);
        $topScores = array_slice($scores, 0, $limit, true);

        $idMap = [];
        foreach ($listings as $l) {
            $idMap[$l->getId()] = $l;
        }

        $result = [];
        foreach ($topIds as $id) {
            if (isset($idMap[$id])) {
                $result[] = $idMap[$id];
            }
        }

        return [
            'listings' => $result,
            'parsed'   => $parsed,
            'scores'   => $topScores,
        ];
    }

    /**
     * Extract structured intent from free-text query.
     */
    private function parseIntent(string $query): array
    {
        $q = mb_strtolower($query);

        // Price extraction  (e.g. "under 800 TND", "moins de 600", "أقل من 500")
        $maxPrice = null;
        if (preg_match('/(?:under|less than|below|moins de|أقل من|max|maximum)\s*[:\-]?\s*(\d+)/u', $q, $m)) {
            $maxPrice = (int) $m[1];
        } elseif (preg_match('/(\d+)\s*(?:tnd|dt|dinar|€|euro)/u', $q, $m)) {
            $maxPrice = (int) $m[1];
        }

        // Bedroom count
        $bedrooms = null;
        if (preg_match('/(\d+)\s*(?:bed|bedroom|chambre|غرفة)/u', $q, $m)) {
            $bedrooms = (int) $m[1];
        } elseif (str_contains($q, 'studio')) {
            $bedrooms = 0;
        }

        // Property type
        $type = null;
        foreach (['studio', 'apartment', 'appartement', 'house', 'maison', 'room', 'chambre', 'shared'] as $t) {
            if (str_contains($q, $t)) { $type = $t; break; }
        }

        // Amenities keywords
        $amenityMap = [
            'wifi' => ['wifi', 'wi-fi', 'internet', 'wireless'],
            'parking' => ['parking', 'garage', 'car'],
            'furnished' => ['furnished', 'meublé', 'meublee', 'مفروش'],
            'air_conditioning' => ['ac', 'air conditioning', 'climatisation', 'clim'],
            'elevator' => ['elevator', 'lift', 'ascenseur'],
            'balcony' => ['balcony', 'balcon', 'terrace', 'terrasse'],
            'washing_machine' => ['washing machine', 'laundry', 'lave-linge'],
        ];
        $amenities = [];
        foreach ($amenityMap as $key => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($q, $kw)) { $amenities[] = $key; break; }
            }
        }

        // City extraction — simple: look for known Tunisian cities
        $cities = ['tunis', 'sfax', 'sousse', 'monastir', 'bizerte', 'nabeul', 'gabes', 'ariana', 'la marsa', 'carthage'];
        $city = null;
        foreach ($cities as $c) {
            if (str_contains($q, $c)) { $city = $c; break; }
        }

        // Quality keywords
        $quiet = str_contains($q, 'quiet') || str_contains($q, 'calme') || str_contains($q, 'هادئ');

        return [
            'max_price'     => $maxPrice,
            'bedrooms'      => $bedrooms,
            'property_type' => $type,
            'amenities'     => $amenities,
            'city'          => $city,
            'quiet'         => $quiet,
            'original'      => $query,
        ];
    }

    private function buildListingText(Listing $listing): string
    {
        return implode(' ', array_filter([
            $listing->getTitle(),
            $listing->getDescription(),
            $listing->getCity(),
            $listing->getPropertyType(),
            $listing->getBedrooms() . ' bedrooms',
            $listing->getPrice() . ' TND',
            implode(' ', method_exists($listing, 'getAmenities') ? ($listing->getAmenities() ?? []) : []),
        ]));
    }
}
