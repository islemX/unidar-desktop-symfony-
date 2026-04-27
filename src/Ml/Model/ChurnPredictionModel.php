<?php

namespace App\Ml\Model;

use App\Ml\Storage\ModelRepository;
use Rubix\ML\Classifiers\GradientBoost;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;

class ChurnPredictionModel
{
    public const MODEL_NAME = 'churn_prediction';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $samples, array $labels): array
    {
        $dataset   = new Labeled($samples, $labels); // labels: 'churned'|'retained'
        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new GradientBoost(new ClassificationTree(5), 150, 0.08)
        );
        $estimator->train($dataset);
        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'trained_at' => date('Y-m-d H:i:s'),
            'samples'    => count($samples),
        ]);
        return ['status' => 'trained', 'samples' => count($samples)];
    }

    public function predictProbability(array $features): float
    {
        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $dataset   = new Unlabeled([$this->toVector($features)]);
        $probs     = $estimator->proba($dataset);
        return (float) ($probs[0]['churned'] ?? 0.0);
    }

    private function toVector(array $f): array
    {
        return [
            (float) ($f['days_since_login']     ?? 0),
            (float) ($f['inactive_30_days']      ?? 0),
            (float) ($f['inactive_60_days']      ?? 0),
            (float) ($f['session_count_30d']     ?? 0),
            (float) ($f['listing_views_30d']     ?? 0),
            (float) ($f['messages_sent_30d']     ?? 0),
            (float) ($f['applications_30d']      ?? 0),
            (float) ($f['has_active_booking']    ?? 0),
            (float) ($f['profile_complete']      ?? 0),
            (float) ($f['account_age_days']      ?? 30),
            (float) ($f['notification_opt_out']  ?? 0),
        ];
    }
}
