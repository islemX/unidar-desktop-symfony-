<?php

namespace App\Ml\Model;

use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Regressors\GradientBoost;
use Rubix\ML\Regressors\RegressionTree;
use Rubix\ML\Transformers\ZScaleStandardizer;

class ListingPerformanceModel
{
    public const MODEL_NAME = 'listing_performance';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $samples, array $daysToFill): array
    {
        $dataset   = new Labeled($samples, array_map('floatval', $daysToFill));
        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new GradientBoost(new RegressionTree(4), 100, 0.1)
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
        return max(1.0, (float) $preds[0]);
    }
}
