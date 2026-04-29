<?php

namespace App\Ml\Model;

use App\Ml\Storage\ModelRepository;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;

class FraudDetectionModel
{
    public const MODEL_NAME = 'fraud_detection';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $signalSets, array $labels): array
    {
        $samples = array_map([$this, 'signalsToVector'], $signalSets);
        $dataset = new Labeled($samples, $labels); // labels: 'fraud'|'legit'

        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new RandomForest(new ClassificationTree(5), 100, 0.3)
        );
        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'trained_at'   => date('Y-m-d H:i:s'),
            'sample_count' => count($samples),
        ]);

        return ['samples' => count($samples), 'status' => 'trained'];
    }

    /**
     * Returns fraud probability 0–100.
     */
    public function predict(array $signals): int
    {
        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $dataset   = new Unlabeled([$this->signalsToVector($signals)]);
        $probs     = $estimator->proba($dataset);
        return (int) round(($probs[0]['fraud'] ?? 0.0) * 100);
    }

    private function signalsToVector(array $s): array
    {
        return [
            (float) ($s['price_ratio']       ?? 1.0),
            (float) ($s['price_too_low']      ?? 0.0),
            (float) ($s['account_age_days']   ?? 365),
            (float) ($s['new_account']        ?? 0.0),
            (float) ($s['desc_length']        ?? 0.5),
            (float) ($s['desc_short']         ?? 0.0),
            (float) ($s['photo_count']        ?? 3),
            (float) ($s['no_photos']          ?? 0.0),
            (float) ($s['vague_location']     ?? 0.0),
            (float) ($s['urgency_keywords']   ?? 0.0),
            (float) ($s['owner_listing_count']?? 1),
            (float) ($s['listing_burst']      ?? 0.0),
            (float) ($s['missing_price']      ?? 0.0),
            (float) ($s['missing_area']       ?? 0.0),
        ];
    }

    public static function generateSyntheticSamples(): array
    {
        $samples = [];
        $labels  = [];

        // Legit listings
        for ($i = 0; $i < 80; $i++) {
            $samples[] = [
                'price_ratio' => 0.9 + mt_rand(0, 30) / 100,
                'price_too_low' => 0.0,
                'account_age_days' => rand(30, 1000),
                'new_account' => 0.0,
                'desc_length' => 0.7,
                'desc_short' => 0.0,
                'photo_count' => rand(3, 8),
                'no_photos' => 0.0,
                'vague_location' => 0.0,
                'urgency_keywords' => 0.0,
                'owner_listing_count' => rand(1, 5),
                'listing_burst' => 0.0,
                'missing_price' => 0.0,
                'missing_area' => 0.0,
            ];
            $labels[] = 'legit';
        }

        // Fraud listings
        for ($i = 0; $i < 40; $i++) {
            $samples[] = [
                'price_ratio' => 0.3 + mt_rand(0, 20) / 100,
                'price_too_low' => 1.0,
                'account_age_days' => rand(0, 6),
                'new_account' => 1.0,
                'desc_length' => 0.1,
                'desc_short' => 1.0,
                'photo_count' => 0,
                'no_photos' => 1.0,
                'vague_location' => 1.0,
                'urgency_keywords' => (float) rand(0, 1),
                'owner_listing_count' => rand(8, 20),
                'listing_burst' => 1.0,
                'missing_price' => 0.0,
                'missing_area' => 1.0,
            ];
            $labels[] = 'fraud';
        }

        return ['samples' => $samples, 'labels' => $labels];
    }
}
