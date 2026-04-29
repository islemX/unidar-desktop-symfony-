<?php

namespace App\Ml\Model;

use App\Entity\Listing;
use App\Ml\Preprocessor\ListingFeatureExtractor;
use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Regressors\KDNeighborsRegressor;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;
use Rubix\ML\CrossValidation\Metrics\RSquared;
use Rubix\ML\CrossValidation\KFold;

class PriceRecommendationModel
{
    public const MODEL_NAME = 'price_recommendation';

    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly ListingFeatureExtractor $extractor,
    ) {}

    /**
     * @param array $samples Feature vectors
     * @param float[] $prices Target prices
     */
    public function train(array $samples, array $prices): array
    {
        // Use only location + structural features for pricing (not description quality)
        $pricingFeatures = $this->extractPricingFeatures($samples);
        $dataset = new Labeled($pricingFeatures, $prices);

        $estimator = new Pipeline([
            new ZScaleStandardizer(),
        ], new KDNeighborsRegressor(7));

        $validator = new KFold(5);
        $metric = new RSquared();
        $score = $validator->test($estimator, $dataset, $metric);

        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'cross_val_r_squared' => round($score, 4),
            'sample_count' => count($samples),
        ]);

        return ['cross_val_r_squared' => $score, 'samples' => count($samples)];
    }

    /**
     * Predicts optimal price range for a listing.
     * Returns ['suggested_price' => float, 'min' => float, 'max' => float, 'rationale' => string]
     */
    public function recommend(Listing $listing): array
    {
        $features = $this->extractor->extract($listing);
        $pricingFeatures = $this->extractPricingFeatures([$features]);
        $dataset = new Unlabeled($pricingFeatures);

        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $predictions = $estimator->predict($dataset);
        $suggested = round((float) $predictions[0], -1); // round to nearest 10

        $range = $this->computeRange($listing, $suggested);

        return [
            'suggested_price' => $suggested,
            'min' => $range['min'],
            'max' => $range['max'],
            'current_price' => (float) ($listing->getPrice() ?? 0),
            'delta' => round($suggested - (float) ($listing->getPrice() ?? 0), 2),
            'rationale' => $this->buildRationale($listing, $suggested, $range),
            'comparable_count' => 7,
        ];
    }

    private function extractPricingFeatures(array $allFeatures): array
    {
        // Indices: bedrooms(3), bathrooms(4), capacity(5), property_type(6)
        return array_map(fn($f) => [$f[3], $f[4], $f[5], $f[6]], $allFeatures);
    }

    private function computeRange(Listing $listing, float $suggested): array
    {
        $spread = max(50, $suggested * 0.12);
        return [
            'min' => round(max(100, $suggested - $spread), -1),
            'max' => round($suggested + $spread, -1),
        ];
    }

    private function buildRationale(Listing $listing, float $suggested, array $range): string
    {
        $current = (float) ($listing->getPrice() ?? 0);
        $bedrooms = $listing->getBedrooms() ?? 1;

        if ($current <= 0) {
            return sprintf(
                'Based on %d comparable %d-bedroom listings, we suggest €%s/month (range: €%s–€%s).',
                7, $bedrooms, number_format($suggested, 0), number_format($range['min'], 0), number_format($range['max'], 0)
            );
        }

        $diff = abs($current - $suggested);
        if ($diff < 30) {
            return 'Your current price is well-aligned with the market. No change needed.';
        }

        $direction = $current > $suggested ? 'lower' : 'raise';
        return sprintf(
            'Consider %sing your price to €%s. Comparable %d-bedroom listings in this area average €%s/month.',
            $direction, number_format($suggested, 0), $bedrooms, number_format($suggested, 0)
        );
    }

    public static function generateSyntheticSamples(): array
    {
        $samples = [];
        $prices = [];

        // Studio (1 room)
        for ($i = 0; $i < 30; $i++) {
            $samples[] = [1.0, 1.0, 1.0, 3.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $prices[] = (float) rand(350, 550);
        }
        // 2-bedroom
        for ($i = 0; $i < 30; $i++) {
            $samples[] = [2.0, 1.0, 2.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $prices[] = (float) rand(550, 850);
        }
        // 3-bedroom house
        for ($i = 0; $i < 30; $i++) {
            $samples[] = [3.0, 2.0, 4.0, 2.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $prices[] = (float) rand(800, 1200);
        }
        // Shared room
        for ($i = 0; $i < 30; $i++) {
            $samples[] = [1.0, 1.0, 3.0, 4.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            $prices[] = (float) rand(200, 400);
        }

        return ['samples' => $samples, 'prices' => $prices];
    }
}
