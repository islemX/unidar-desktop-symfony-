<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Ml\Model\DynamicPricingModel;
use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;

/**
 * Feature 12: Dynamic Market Pricing (Real-time)
 * Adjusts price recommendations based on current vacancy, season, competition.
 */
class DynamicPricingService
{
    // Tunisia academic calendar peak months
    private const PEAK_MONTHS    = [8, 9, 10];   // Back-to-school surge
    private const LOW_MONTHS     = [6, 7];        // Summer departure
    private const EXAM_MONTHS    = [1, 5, 6];     // Some students looking for short-term

    public function __construct(
        private readonly ListingRepository $listingRepository,
        private readonly ModelRepository   $modelRepository,
        private readonly DynamicPricingModel $model,
    ) {}

    /**
     * Returns a dynamic price recommendation with market context.
     */
    public function recommend(Listing $listing): array
    {
        $market  = $this->getMarketContext($listing);
        $base    = (float) ($listing->getPrice() ?? 0);
        $median  = $market['median_price'];

        // Seasonal multiplier
        $month     = (int) date('n');
        $seasonal  = $this->seasonalMultiplier($month);

        // Demand pressure: vacancy rate in same area/type
        $demandAdj = $this->demandAdjustment($market);

        // Competition: how many similar listings are active
        $compAdj   = $this->competitionAdjustment($market);

        $suggestedMultiplier = $seasonal * $demandAdj * $compAdj;
        $suggested = round($median * $suggestedMultiplier, -1);

        // Guardrails: don't suggest more than ±40% from median
        $suggested = max($median * 0.6, min($median * 1.4, $suggested));

        $delta         = $suggested - $base;
        $urgency       = $this->urgencyMessage($market, $month, $delta);

        return [
            'suggested_price'    => (int) $suggested,
            'current_price'      => (int) $base,
            'delta'              => (int) $delta,
            'direction'          => $delta > 15 ? 'raise' : ($delta < -15 ? 'lower' : 'hold'),
            'seasonal_factor'    => round($seasonal, 2),
            'demand_factor'      => round($demandAdj, 2),
            'competition_factor' => round($compAdj, 2),
            'market'             => $market,
            'urgency'            => $urgency,
            'next_review'        => date('Y-m-d', strtotime('+7 days')),
        ];
    }

    public function getMarketContext(Listing $listing): array
    {
        $similar = $this->listingRepository->findBy([
            'propertyType' => $listing->getPropertyType(),
            'city'         => $listing->getCity(),
            'status'       => 'active',
        ], null, 50);

        $prices = array_map(fn($l) => (float) $l->getPrice(), $similar);
        sort($prices);
        $count  = count($prices);

        return [
            'active_listings'  => $count,
            'median_price'     => $count > 0 ? (float) $prices[(int)($count / 2)] : (float)($listing->getPrice() ?? 500),
            'min_price'        => $count > 0 ? (float) $prices[0]                 : 0.0,
            'max_price'        => $count > 0 ? (float) end($prices)               : 0.0,
            'avg_price'        => $count > 0 ? round(array_sum($prices) / $count) : 0.0,
            'vacancy_rate'     => $this->estimateVacancyRate($listing),
            'month'            => (int) date('n'),
            'season'           => $this->seasonName(),
        ];
    }

    private function seasonalMultiplier(int $month): float
    {
        if (in_array($month, self::PEAK_MONTHS))  return 1.12;
        if (in_array($month, self::LOW_MONTHS))   return 0.92;
        if (in_array($month, self::EXAM_MONTHS))  return 1.04;
        return 1.0;
    }

    private function demandAdjustment(array $market): float
    {
        $vacancy = $market['vacancy_rate'];
        if ($vacancy > 0.35) return 0.93; // High supply → lower price
        if ($vacancy < 0.10) return 1.08; // Scarce supply → raise price
        return 1.0;
    }

    private function competitionAdjustment(array $market): float
    {
        $count = $market['active_listings'];
        if ($count > 30) return 0.96; // Lots of competition
        if ($count < 5)  return 1.05; // Little competition
        return 1.0;
    }

    private function estimateVacancyRate(Listing $listing): float
    {
        $total = $this->listingRepository->count(['propertyType' => $listing->getPropertyType()]);
        $active = $this->listingRepository->count([
            'propertyType' => $listing->getPropertyType(),
            'status'       => 'active',
        ]);
        return $total > 0 ? round($active / $total, 2) : 0.2;
    }

    private function urgencyMessage(array $market, int $month, float $delta): string
    {
        if (in_array($month, self::PEAK_MONTHS) && $delta > 0) {
            return '🔥 Back-to-school season — high demand. Raise price now to maximise revenue.';
        }
        if (in_array($month, self::LOW_MONTHS) && $delta < 0) {
            return '❄️ Low season — consider a temporary discount to avoid vacancy.';
        }
        if ($market['vacancy_rate'] < 0.10) {
            return '⚡ Very low supply in your area — you can charge above median.';
        }
        if ($market['active_listings'] > 25) {
            return '📊 High competition — competitive pricing will fill your listing faster.';
        }
        return '✅ Market is stable — current price is well-positioned.';
    }

    private function seasonName(): string
    {
        $m = (int) date('n');
        return match (true) {
            in_array($m, [9, 10, 11]) => 'autumn',
            in_array($m, [12, 1, 2])  => 'winter',
            in_array($m, [3, 4, 5])   => 'spring',
            default                    => 'summer',
        };
    }
}
