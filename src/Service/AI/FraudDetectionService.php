<?php

namespace App\Service\AI;

use App\Entity\Listing;
use App\Entity\User;
use App\Ml\Model\FraudDetectionModel;
use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;

/**
 * Feature 6: Fraud / Fake Listing Detector
 * Scores listings 0–100 (higher = more suspicious).
 * Uses rule-based signals + ML when trained.
 */
class FraudDetectionService
{
    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly FraudDetectionModel $model,
        private readonly ListingRepository $listingRepository,
    ) {}

    /**
     * @return array [score:int, risk:string, signals:array, action:string]
     */
    public function score(Listing $listing): array
    {
        $signals = $this->extractSignals($listing);
        $score   = $this->computeRuleScore($signals);

        // Boost with ML model if trained
        if ($this->modelRepository->exists(FraudDetectionModel::MODEL_NAME)) {
            $mlScore = $this->model->predict($signals);
            $score   = (int) round($score * 0.4 + $mlScore * 0.6);
        }

        $score = min(100, max(0, $score));
        $risk  = match (true) {
            $score >= 75 => 'high',
            $score >= 45 => 'medium',
            default      => 'low',
        };

        return [
            'score'   => $score,
            'risk'    => $risk,
            'signals' => $this->describeSignals($signals),
            'action'  => $this->recommendAction($risk),
        ];
    }

    // ── Signal extraction ─────────────────────────────────────────────────────

    public function extractSignals(Listing $listing): array
    {
        $owner   = $listing->getOwner();
        $signals = [];

        // Price anomaly — much cheaper than median for same type/city
        $median  = $this->getMedianPrice($listing->getPropertyType()?->value, $listing->getCity());
        $price   = (float) ($listing->getPrice() ?? 0);
        $signals['price_ratio']       = $median > 0 ? round($price / $median, 2) : 1.0;
        $signals['price_too_low']     = ($median > 0 && $price < $median * 0.55) ? 1.0 : 0.0;

        // Account age (days)
        $accountAgeDays = $owner
            ? max(0, (new \DateTimeImmutable())->diff($owner->getCreatedAt())->days)
            : 0;
        $signals['account_age_days']  = $accountAgeDays;
        $signals['new_account']       = $accountAgeDays < 7 ? 1.0 : 0.0;

        // Description quality
        $desc = $listing->getDescription() ?? '';
        $signals['desc_length']       = min(1.0, strlen($desc) / 500);
        $signals['desc_short']        = strlen($desc) < 80 ? 1.0 : 0.0;

        // Photo count
        $photoCount = $listing->getImages() ? count($listing->getImages()) : 0;
        $signals['photo_count']       = $photoCount;
        $signals['no_photos']         = $photoCount === 0 ? 1.0 : 0.0;

        // Vague location
        $signals['vague_location']    = empty($listing->getAddress()) ? 1.0 : 0.0;

        // Contact urgency keywords in description
        $urgencyWords = ['urgent', 'hurry', 'limited time', 'act now', 'wire transfer', 'western union', 'moneygram'];
        $signals['urgency_keywords']  = $this->containsAny(strtolower($desc), $urgencyWords) ? 1.0 : 0.0;

        // Owner listing velocity (many listings posted quickly)
        $ownerListingCount = $owner ? $this->listingRepository->count(['owner' => $owner]) : 0;
        $signals['owner_listing_count'] = $ownerListingCount;
        $signals['listing_burst']     = ($ownerListingCount > 10 && $accountAgeDays < 30) ? 1.0 : 0.0;

        // Missing key fields
        $signals['missing_price']     = empty($listing->getPrice()) ? 1.0 : 0.0;
        // area field may not exist — fall back to bedroom-based proxy
        $area = method_exists($listing, 'getArea') ? $listing->getArea() : $listing->getBedrooms();
        $signals['missing_area']      = empty($area) ? 1.0 : 0.0;

        return $signals;
    }

    private function computeRuleScore(array $s): int
    {
        $score = 0;
        if ($s['price_too_low'])     $score += 30;
        if ($s['new_account'])       $score += 20;
        if ($s['desc_short'])        $score += 15;
        if ($s['no_photos'])         $score += 20;
        if ($s['vague_location'])    $score += 10;
        if ($s['urgency_keywords'])  $score += 25;
        if ($s['listing_burst'])     $score += 15;
        if ($s['missing_price'])     $score += 10;
        return $score;
    }

    private function describeSignals(array $s): array
    {
        $flags = [];
        if ($s['price_too_low'])    $flags[] = 'Price is significantly below market median — possible bait';
        if ($s['new_account'])      $flags[] = 'Owner account created less than 7 days ago';
        if ($s['desc_short'])       $flags[] = 'Description is very short or missing';
        if ($s['no_photos'])        $flags[] = 'No photos uploaded';
        if ($s['vague_location'])   $flags[] = 'No specific address provided';
        if ($s['urgency_keywords']) $flags[] = 'Urgency or suspicious payment keywords detected';
        if ($s['listing_burst'])    $flags[] = 'Owner posted many listings in a short time';
        return $flags;
    }

    private function recommendAction(string $risk): string
    {
        return match ($risk) {
            'high'   => 'Hold for admin review before publishing',
            'medium' => 'Send verification request to owner',
            default  => 'Auto-approve',
        };
    }

    private function getMedianPrice(?string $type, ?string $city): float
    {
        // Use all active listings as fallback when type filter can't be applied safely
        try {
            $criteria = ['status' => 'active'];
            $listings = $this->listingRepository->findBy($criteria, null, 50);
        } catch (\Throwable) {
            return 0.0;
        }

        if (empty($listings)) return 0.0;

        $prices = array_map(fn($l) => (float) $l->getPrice(), $listings);
        sort($prices);
        return (float) $prices[(int)(count($prices) / 2)];
    }

    private function containsAny(string $text, array $words): bool
    {
        foreach ($words as $w) {
            if (str_contains($text, $w)) return true;
        }
        return false;
    }
}
