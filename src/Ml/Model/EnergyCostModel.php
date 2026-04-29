<?php

namespace App\Ml\Model;

use App\Entity\Listing;
use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Pipeline;
use Rubix\ML\Regressors\GradientBoost;
use Rubix\ML\Regressors\RegressionTree;
use Rubix\ML\Transformers\ZScaleStandardizer;

class EnergyCostModel
{
    public const MODEL_NAME = 'energy_cost';

    public function __construct(private readonly ModelRepository $modelRepository) {}

    public function train(array $samples, array $costs): array
    {
        $dataset   = new Labeled($samples, $costs);
        $estimator = new Pipeline(
            [new ZScaleStandardizer()],
            new GradientBoost(new RegressionTree(4), 100, 0.1)
        );
        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'trained_at'   => date('Y-m-d H:i:s'),
            'sample_count' => count($samples),
        ]);

        return ['status' => 'trained', 'samples' => count($samples)];
    }

    public function predict(Listing $listing): array
    {
        $estimator = $this->modelRepository->load(self::MODEL_NAME);
        $dataset   = new Unlabeled([$this->extract($listing)]);
        $preds     = $estimator->predict($dataset);
        $total     = max(0, (float) $preds[0]);

        return [
            'electricity_monthly' => (int) round($total * 0.7),
            'water_monthly'       => (int) round($total * 0.3),
            'total_monthly'       => (int) round($total),
            'total_annual'        => (int) round($total * 12),
            'model_used'          => 'gradient_boost',
        ];
    }

    private function extract(Listing $listing): array
    {
        // Listing entity has no area/floor/amenities columns — use safe proxies
        $bedrooms  = (int) ($listing->getBedrooms() ?? 1);
        $area      = (float) (method_exists($listing, 'getArea')      ? ($listing->getArea()  ?? $bedrooms * 25) : $bedrooms * 25);
        $floor     = (float) (method_exists($listing, 'getFloor')     ? ($listing->getFloor() ?? 2)              : 2);
        $amenities = method_exists($listing, 'getAmenities') ? ($listing->getAmenities() ?? []) : [];

        return [
            $area,
            (float) $bedrooms,
            $floor,
            (float) in_array('air_conditioning',  $amenities),
            (float) in_array('washing_machine',   $amenities),
            (float) in_array('solar_water_heater', $amenities),
            (float) in_array('electric_heating',  $amenities),
        ];
    }
}
