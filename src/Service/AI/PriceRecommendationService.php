<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Ml\Model\PriceRecommendationModel;
use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;

class PriceRecommendationService
{
    public function __construct(
        private readonly PriceRecommendationModel $model,
        private readonly ModelRepository $modelRepository,
        private readonly ListingRepository $listingRepository,
    ) {}

    public function recommendPrice(Listing $listing): array
    {
        if (!$this->modelRepository->exists(PriceRecommendationModel::MODEL_NAME)) {
            return $this->fallbackRecommendation($listing);
        }

        return $this->model->recommend($listing);
    }

    public function isModelReady(): bool
    {
        return $this->modelRepository->exists(PriceRecommendationModel::MODEL_NAME);
    }

    public function getModelInfo(): array
    {
        return $this->modelRepository->getMetadata(PriceRecommendationModel::MODEL_NAME);
    }

    /**
     * Median-based fallback when model isn't trained.
     * Uses DB listings with same bedroom count + property type.
     */
    private function fallbackRecommendation(Listing $listing): array
    {
        $comparables = $this->listingRepository->findBy([
            'bedrooms' => $listing->getBedrooms(),
            'propertyType' => $listing->getPropertyType(),
            'status' => 'active',
        ], null, 20);

        if (count($comparables) < 3) {
            return [
                'suggested_price' => (float) ($listing->getPrice() ?? 0),
                'min' => null,
                'max' => null,
                'current_price' => (float) ($listing->getPrice() ?? 0),
                'delta' => 0,
                'rationale' => 'Not enough comparable listings yet. Price based on your input.',
                'comparable_count' => 0,
                'model_used' => 'no_data_fallback',
            ];
        }

        $prices = array_map(fn($l) => (float) $l->getPrice(), $comparables);
        sort($prices);
        $median = $prices[(int) (count($prices) / 2)];
        $min = round($prices[0] * 0.9, -1);
        $max = round(end($prices) * 1.1, -1);
        $current = (float) ($listing->getPrice() ?? 0);

        return [
            'suggested_price' => round($median, -1),
            'min' => $min,
            'max' => $max,
            'current_price' => $current,
            'delta' => round($median - $current, 2),
            'rationale' => sprintf(
                'Based on %d comparable listings, the median price is €%s/month.',
                count($comparables), number_format($median, 0)
            ),
            'comparable_count' => count($comparables),
            'model_used' => 'median_fallback',
        ];
    }
}
