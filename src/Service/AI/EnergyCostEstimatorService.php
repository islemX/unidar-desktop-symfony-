<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Ml\Model\EnergyCostModel;
use App\Ml\Storage\ModelRepository;

/**
 * Feature 7: Energy Cost Estimator
 * Predicts monthly electricity + water bills from apartment features.
 */
class EnergyCostEstimatorService
{
    // Tunisia average kWh cost (TND) - 2024
    private const KWH_COST = 0.19;
    private const WATER_COST_PER_M3 = 0.85;

    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly EnergyCostModel $model,
    ) {}

    public function estimate(Listing $listing): array
    {
        if ($this->modelRepository->exists(EnergyCostModel::MODEL_NAME)) {
            return $this->model->predict($listing);
        }

        return $this->ruleBasedEstimate($listing);
    }

    private function ruleBasedEstimate(Listing $listing): array
    {
        $area      = (float) ($listing->getArea() ?? 60);
        $bedrooms  = (int) ($listing->getBedrooms() ?? 1);
        $amenities = $listing->getAmenities() ?? [];
        $floor     = (int) ($listing->getFloor() ?? 2);

        // Base load: lighting + appliances (kWh/month)
        $baseKwh = $area * 0.8 + $bedrooms * 15;

        // AC adds significant consumption
        if (in_array('air_conditioning', $amenities) || in_array('ac', $amenities)) {
            $baseKwh += $area * 1.2; // summer avg (Tunisia)
        }

        // Electric water heater
        if (!in_array('solar_water_heater', $amenities)) {
            $baseKwh += 40;
        }

        // Higher floors → more heating/cooling loss
        if ($floor > 5) $baseKwh *= 1.1;

        // Washing machine
        if (in_array('washing_machine', $amenities)) {
            $baseKwh += 20;
        }

        $electricMonthly = round($baseKwh * self::KWH_COST, 0);

        // Water: ~5 m³/person/month
        $occupants     = max(1, $bedrooms);
        $waterMonthly  = round($occupants * 5 * self::WATER_COST_PER_M3, 0);

        $total = $electricMonthly + $waterMonthly;

        // Efficiency rating
        $kwhPerM2  = $baseKwh / max(1, $area);
        $rating    = match (true) {
            $kwhPerM2 < 1.2 => ['label' => 'A', 'color' => '#22c55e'],
            $kwhPerM2 < 2.0 => ['label' => 'B', 'color' => '#84cc16'],
            $kwhPerM2 < 3.0 => ['label' => 'C', 'color' => '#eab308'],
            $kwhPerM2 < 4.0 => ['label' => 'D', 'color' => '#f97316'],
            default          => ['label' => 'E', 'color' => '#ef4444'],
        };

        $tips = $this->savingTips($amenities, $kwhPerM2);

        return [
            'electricity_monthly' => (int) $electricMonthly,
            'water_monthly'       => (int) $waterMonthly,
            'total_monthly'       => (int) $total,
            'total_annual'        => (int) ($total * 12),
            'kwh_per_month'       => (int) $baseKwh,
            'efficiency_rating'   => $rating,
            'tips'                => $tips,
            'model_used'          => 'rule_based',
            'disclaimer'          => 'Estimates only. Actual costs vary with usage habits and season.',
        ];
    }

    private function savingTips(array $amenities, float $kwhPerM2): array
    {
        $tips = [];
        if ($kwhPerM2 > 2.0) {
            $tips[] = 'Install LED lighting — saves up to 20% on electricity';
        }
        if (!in_array('solar_water_heater', $amenities)) {
            $tips[] = 'Solar water heater can cut water-heating costs by 60%';
        }
        if (in_array('air_conditioning', $amenities)) {
            $tips[] = 'Set AC to 26°C in summer — each degree lower adds ~8% to your bill';
        }
        $tips[] = 'Unplug chargers and appliances on standby — saves ~5% monthly';
        return $tips;
    }
}
