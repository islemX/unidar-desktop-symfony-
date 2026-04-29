<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Ml\Model\ListingPerformanceModel;
use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;

/**
 * Feature 16: Listing Performance Predictor
 * "At current price+photos, this listing will fill in ~23 days."
 */
class ListingPerformanceService
{
    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly ListingPerformanceModel $model,
        private readonly ListingRepository $listingRepository,
    ) {}

    public function predict(Listing $listing): array
    {
        $features    = $this->extractFeatures($listing);
        $daysToFill  = $this->modelRepository->exists(ListingPerformanceModel::MODEL_NAME)
            ? (int) $this->model->predict($features)
            : $this->ruleBasedDays($listing);

        $daysToFill = max(1, min(120, $daysToFill));
        $score      = $this->performanceScore($listing, $daysToFill);

        return [
            'days_to_fill'    => $daysToFill,
            'fill_date'       => date('Y-m-d', strtotime("+{$daysToFill} days")),
            'performance_score' => $score,
            'grade'           => $this->grade($score),
            'impact_actions'  => $this->impactActions($listing, $daysToFill),
            'comparisons'     => $this->whatIfComparisons($listing, $daysToFill, $features),
        ];
    }

    private function extractFeatures(Listing $listing): array
    {
        $photoCount = $listing->getImages() ? count($listing->getImages()) : 0;
        $descLen    = strlen($listing->getDescription() ?? '');
        $bedrooms   = (int) ($listing->getBedrooms() ?? 1);
        $amenCount  = method_exists($listing, 'getAmenities') ? count($listing->getAmenities() ?? []) : 0;
        $area       = (float) (method_exists($listing, 'getArea') ? ($listing->getArea() ?? $bedrooms * 25) : $bedrooms * 25);
        $median     = $this->getMedianPrice($listing);
        $priceRatio = $median > 0 ? (float)($listing->getPrice() ?? 0) / $median : 1.0;

        return [
            $priceRatio,
            (float) $photoCount,
            min(1.0, $descLen / 500),
            (float) $amenCount,
            (float) $bedrooms,
            (float) ($listing->getBathrooms() ?? 1),
            $area,
            (float) (int) date('n'), // month
        ];
    }

    private function ruleBasedDays(Listing $listing): int
    {
        $base       = 21;
        $photoCount = $listing->getImages() ? count($listing->getImages()) : 0;
        $descLen    = strlen($listing->getDescription() ?? '');
        $median     = $this->getMedianPrice($listing);
        $price      = (float) ($listing->getPrice() ?? 0);

        if ($photoCount === 0)    $base += 18;
        elseif ($photoCount < 3)  $base += 8;
        elseif ($photoCount >= 6) $base -= 5;

        if ($descLen < 100)       $base += 10;
        elseif ($descLen > 300)   $base -= 3;

        if ($median > 0) {
            $ratio = $price / $median;
            if ($ratio > 1.2)     $base += 14;
            elseif ($ratio < 0.9) $base -= 5;
        }

        $month = (int) date('n');
        if (in_array($month, [8, 9, 10])) $base -= 7;  // Peak season
        if (in_array($month, [6, 7]))     $base += 10; // Low season

        return $base;
    }

    private function performanceScore(Listing $listing, int $days): int
    {
        // 1 day = 100, 120 days = 0
        return max(0, min(100, (int) round((120 - $days) / 120 * 100)));
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A – Will fill very quickly',
            $score >= 60 => 'B – Good performance expected',
            $score >= 40 => 'C – Average, some improvements possible',
            $score >= 20 => 'D – Slow to fill — action recommended',
            default      => 'F – Needs significant improvement',
        };
    }

    private function impactActions(Listing $listing, int $days): array
    {
        $actions = [];
        $photos  = $listing->getImages() ? count($listing->getImages()) : 0;
        $desc    = strlen($listing->getDescription() ?? '');
        $median  = $this->getMedianPrice($listing);
        $price   = (float) ($listing->getPrice() ?? 0);

        if ($photos < 4) {
            $saving = min(14, $days - max(1, $days - 12));
            $actions[] = ['action' => 'Add ' . (4 - $photos) . ' more photos', 'days_saved' => $saving, 'priority' => 'high'];
        }
        if ($desc < 150) {
            $actions[] = ['action' => 'Improve description (add 150+ characters)', 'days_saved' => 7, 'priority' => 'medium'];
        }
        if ($median > 0 && $price > $median * 1.15) {
            $actions[] = [
                'action'    => 'Reduce price by ' . (int)($price - $median) . ' TND to match median',
                'days_saved' => 10,
                'priority'  => 'high',
            ];
        }
        $amenities = method_exists($listing, 'getAmenities') ? ($listing->getAmenities() ?? []) : [];
        if (empty($amenities)) {
            $actions[] = ['action' => 'List your amenities (WiFi, AC, parking…)', 'days_saved' => 4, 'priority' => 'low'];
        }

        usort($actions, fn($a, $b) => $b['days_saved'] <=> $a['days_saved']);
        return $actions;
    }

    private function whatIfComparisons(Listing $listing, int $baseDays, array $features): array
    {
        return [
            'improve_photos' => max(1, $baseDays - 10) . ' days',
            'drop_price_10pct' => max(1, $baseDays - 8) . ' days',
            'peak_season' => max(1, $baseDays - 6) . ' days',
        ];
    }

    private function getMedianPrice(Listing $listing): float
    {
        $listings = $this->listingRepository->findBy([
            'propertyType' => $listing->getPropertyType(),
            'status'       => 'active',
        ], null, 30);

        if (empty($listings)) return 0.0;
        $prices = array_map(fn($l) => (float)$l->getPrice(), $listings);
        sort($prices);
        return (float)$prices[(int)(count($prices) / 2)];
    }
}
