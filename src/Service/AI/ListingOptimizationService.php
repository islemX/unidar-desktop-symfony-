<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Ml\Model\ListingQualityModel;
use App\Ml\Storage\ModelRepository;

class ListingOptimizationService
{
    public function __construct(
        private readonly ListingQualityModel $model,
        private readonly ModelRepository $modelRepository,
    ) {}

    /**
     * Returns a full quality report for a listing.
     */
    public function scoreListingQuality(Listing $listing): array
    {
        if (!$this->modelRepository->exists(ListingQualityModel::MODEL_NAME)) {
            return $this->fallbackScore($listing);
        }

        return $this->model->predict($listing);
    }

    public function isModelReady(): bool
    {
        return $this->modelRepository->exists(ListingQualityModel::MODEL_NAME);
    }

    public function getModelInfo(): array
    {
        return $this->modelRepository->getMetadata(ListingQualityModel::MODEL_NAME);
    }

    /**
     * Rule-based fallback when model has not been trained yet.
     */
    private function fallbackScore(Listing $listing): array
    {
        $score = 40;
        $issues = [];
        $suggestions = [];

        $photoCount = $listing->getImages()->count();
        if ($photoCount >= 8) {
            $score += 20;
        } elseif ($photoCount >= 4) {
            $score += 10;
        } else {
            $issues[] = 'low_photo_count';
            $suggestions[] = sprintf('Add %d more photos for better visibility.', max(1, 5 - $photoCount));
        }

        $wordCount = str_word_count(strip_tags($listing->getDescription() ?? ''));
        if ($wordCount >= 100) {
            $score += 20;
        } elseif ($wordCount >= 50) {
            $score += 10;
        } else {
            $issues[] = 'short_description';
            $suggestions[] = 'Write a more detailed description (100+ words recommended).';
        }

        if ($listing->getAvailableFrom() !== null) {
            $score += 10;
        } else {
            $issues[] = 'missing_availability';
            $suggestions[] = 'Set availability dates to appear in more student searches.';
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'tier' => $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : 'fair'),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'impact_estimate' => count($issues) > 0
                ? sprintf('Addressing these %d issues could increase inquiries by ~%d%%.', count($issues), count($issues) * 12)
                : 'Listing is well-optimized!',
            'model_used' => 'rule_based_fallback',
        ];
    }
}
