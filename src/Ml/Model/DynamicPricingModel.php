<?php

namespace App\Ml\Model;

use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Regressors\GradientBoost;
use Rubix\ML\Regressors\RegressionTree;
use Rubix\ML\Transformers\ZScaleStandardizer;

class DynamicPricingModel
{
    public const MODEL_NAME = 'dynamic_pricing';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $samples, array $prices): array
    {
        $dataset   = new Labeled($samples, $prices);
        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new GradientBoost(new RegressionTree(5), 150, 0.08)
        );
        $estimator->train($dataset);
        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'trained_at' => date('Y-m-d H:i:s'),
            'samples'    => count($samples),
        ]);
        return ['status' => 'trained', 'samples' => count($samples)];
    }

    public function predict(array $features): float
    {
        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $dataset   = new Unlabeled([$features]);
        $preds     = $estimator->predict($dataset);
        return max(0.0, (float) $preds[0]);
    }

    public static function buildFeatures(
        string $propertyType,
        string $city,
        int    $bedrooms,
        float  $area,
        int    $month,
        int    $activeListings,
        float  $vacancyRate,
        float  $medianPrice,
    ): array {
        $typeMap = ['studio' => 0, 'apartment' => 1, 'house' => 2, 'room' => 3, 'shared' => 4];
        return [
            (float) ($typeMap[$propertyType] ?? 1),
            (float) $bedrooms,
            $area,
            (float) $month,
            (float) $activeListings,
            $vacancyRate,
            $medianPrice,
            in_array($month, [8, 9, 10]) ? 1.0 : 0.0, // peak season
            in_array($month, [6, 7]) ? 1.0 : 0.0,      // low season
        ];
    }
}
