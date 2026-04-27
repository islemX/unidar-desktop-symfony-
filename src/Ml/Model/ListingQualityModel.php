<?php

namespace App\Ml\Model;

use App\Entity\Listing;
use App\Ml\Preprocessor\ListingFeatureExtractor;
use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;
use Rubix\ML\CrossValidation\Metrics\Accuracy;
use Rubix\ML\CrossValidation\KFold;

class ListingQualityModel
{
    public const MODEL_NAME = 'listing_quality';
    private const QUALITY_THRESHOLDS = [
        'excellent' => 85,
        'good' => 65,
        'fair' => 45,
    ];

    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly ListingFeatureExtractor $extractor,
    ) {}

    public function train(array $samples, array $labels): array
    {
        $dataset = new Labeled($samples, $labels);

        $estimator = new Pipeline([
            new ZScaleStandardizer(),
        ], new RandomForest(new ClassificationTree(5), 100, 0.2, true));

        $validator = new KFold(5);
        $metric = new Accuracy();
        $score = $validator->test($estimator, $dataset, $metric);

        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'cross_val_accuracy' => round($score, 4),
            'sample_count' => count($samples),
            'feature_names' => $this->extractor->featureNames(),
            'labels' => array_unique($labels),
        ]);

        return ['cross_val_accuracy' => $score, 'samples' => count($samples)];
    }

    /**
     * Predicts a quality tier for a single listing.
     * Returns ['score' => int, 'tier' => string, 'issues' => array, 'suggestions' => array]
     */
    public function predict(Listing $listing): array
    {
        $features = $this->extractor->extract($listing);
        $dataset = new Unlabeled([$features]);

        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $predictions = $estimator->predict($dataset);
        $tier = $predictions[0];

        $score = $this->tierToScore($tier, $features);
        $issues = $this->detectIssues($listing, $features);
        $suggestions = $this->buildSuggestions($issues, $listing);

        return [
            'score' => $score,
            'tier' => $tier,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'impact_estimate' => $this->estimateImpact($issues),
        ];
    }

    private function tierToScore(string $tier, array $features): int
    {
        $base = match ($tier) {
            'excellent' => 90,
            'good' => 72,
            'fair' => 52,
            default => 30,
        };
        // Small adjustment based on photo count (index 0)
        $photoBonus = min(5, (int) ($features[0] / 2));
        return min(100, $base + $photoBonus);
    }

    private function detectIssues(Listing $listing, array $features): array
    {
        $issues = [];
        if ($features[0] < 5) {
            $issues[] = 'low_photo_count';
        }
        if ($features[1] < 50) {
            $issues[] = 'short_description';
        }
        if ($listing->getAvailableFrom() === null) {
            $issues[] = 'missing_availability';
        }
        if ($listing->getGenderPreference() === null) {
            $issues[] = 'no_gender_preference';
        }
        if ($features[9] < 20) {
            $issues[] = 'short_title';
        }
        return $issues;
    }

    private function buildSuggestions(array $issues, Listing $listing): array
    {
        $suggestions = [];
        if (in_array('low_photo_count', $issues)) {
            $current = $listing->getImages()->count();
            $suggestions[] = sprintf('Add %d more photos — listings with 8+ images attract 40%% more inquiries.', max(1, 8 - $current));
        }
        if (in_array('short_description', $issues)) {
            $wordCount = str_word_count(strip_tags($listing->getDescription() ?? ''));
            $suggestions[] = sprintf('Expand your description by ~%d words. Detailed listings convert better.', max(10, 100 - $wordCount));
        }
        if (in_array('missing_availability', $issues)) {
            $suggestions[] = 'Set available-from and available-until dates to appear in more searches.';
        }
        if (in_array('short_title', $issues)) {
            $suggestions[] = 'Use a more descriptive title (e.g., include location, property type, key feature).';
        }
        return $suggestions;
    }

    private function estimateImpact(array $issues): string
    {
        $impactPct = count($issues) * 12;
        if ($impactPct === 0) {
            return 'Your listing is well-optimized!';
        }
        return sprintf('Fix these issues to potentially increase inquiries by ~%d%%.', min(50, $impactPct));
    }

    /**
     * Generates synthetic training samples from listing stats.
     * Used when no labeled data is available yet.
     */
    public static function generateSyntheticSamples(): array
    {
        $samples = [];
        $labels = [];

        // Excellent listings: many photos, long descriptions, full info
        for ($i = 0; $i < 40; $i++) {
            $samples[] = [rand(8, 15), rand(150, 400), rand(500, 1500), rand(1, 4), rand(1, 3), rand(2, 6), rand(1, 5), 1, 1, rand(30, 80)];
            $labels[] = 'excellent';
        }
        // Good listings
        for ($i = 0; $i < 40; $i++) {
            $samples[] = [rand(4, 8), rand(60, 150), rand(300, 1200), rand(1, 3), rand(1, 2), rand(1, 4), rand(1, 4), rand(0, 1), rand(0, 1), rand(20, 50)];
            $labels[] = 'good';
        }
        // Fair/poor listings: few photos, short text, missing data
        for ($i = 0; $i < 40; $i++) {
            $samples[] = [rand(0, 3), rand(10, 60), rand(200, 800), rand(1, 2), 1, rand(1, 2), rand(0, 3), 0, 0, rand(5, 20)];
            $labels[] = 'fair';
        }

        return ['samples' => $samples, 'labels' => $labels];
    }
}
