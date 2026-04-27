<?php

namespace App\Ml\Model;

use App\Ml\Storage\ModelRepository;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;

class BehaviouralAnomalyModel
{
    public const MODEL_NAME = 'behavioural_anomaly';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $signalSets, array $labels): array
    {
        $samples = array_map([$this, 'toVector'], $signalSets);
        $dataset = new Labeled($samples, $labels);

        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new RandomForest(new ClassificationTree(5), 120)
        );
        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'trained_at' => date('Y-m-d H:i:s'),
            'samples'    => count($samples),
        ]);

        return ['status' => 'trained', 'samples' => count($samples)];
    }

    public function predict(array $signals): int
    {
        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $dataset   = new Unlabeled([$this->toVector($signals)]);
        $probs     = $estimator->proba($dataset);
        return (int) round(($probs[0]['anomaly'] ?? 0.0) * 100);
    }

    private function toVector(array $s): array
    {
        return [
            (float) ($s['account_age_days']   ?? 365),
            (float) ($s['new_account']         ?? 0),
            (float) ($s['msg_rate_per_day']    ?? 0),
            (float) ($s['high_msg_rate']       ?? 0),
            (float) ($s['views_per_session']   ?? 0),
            (float) ($s['scraper_pattern']     ?? 0),
            (float) ($s['unique_ip_count']     ?? 1),
            (float) ($s['ip_anomaly']          ?? 0),
            (float) ($s['no_profile_photo']    ?? 0),
            (float) ($s['no_phone']            ?? 0),
            (float) ($s['bulk_contact']        ?? 0),
            (float) ($s['failed_logins']       ?? 0),
            (float) ($s['account_incomplete']  ?? 0),
        ];
    }
}
