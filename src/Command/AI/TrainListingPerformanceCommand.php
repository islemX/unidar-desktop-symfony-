<?php

namespace App\Command\AI;

use App\Ml\Model\ListingPerformanceModel;
use App\Repository\ListingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:listing-performance', description: 'Train the listing fill-time prediction model')]
class TrainListingPerformanceCommand extends Command
{
    public function __construct(
        private readonly ListingPerformanceModel $model,
        private readonly ListingRepository       $listingRepo,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Listing Performance (Fill-Time) Model');

        [$samples, $labels] = $this->generateSyntheticData();
        $io->writeln(sprintf('Synthetic samples: %d', count($samples)));

        // Augment with real listings — compute a rule-based fill-time estimate as label
        $listings = $this->listingRepo->findBy(['status' => 'active'], null, 200);
        $io->writeln(sprintf('Real listings to augment: %d', count($listings)));

        $allPrices = array_map(fn($l) => (float)$l->getPrice(), $listings);
        sort($allPrices);
        $median = count($allPrices) > 0 ? $allPrices[(int)(count($allPrices) / 2)] : 500.0;

        foreach ($listings as $listing) {
            try {
                $price      = (float)($listing->getPrice() ?? 500);
                $priceRatio = $median > 0 ? $price / $median : 1.0;
                $photoCount = $listing->getImages() ? count($listing->getImages()) : 0;
                $descLen    = strlen($listing->getDescription() ?? '');
                $bedrooms   = (float)($listing->getBedrooms() ?? 1);
                $bathrooms  = (float)($listing->getBathrooms() ?? 1);
                $month      = (float)(int)date('n');

                $samples[] = [
                    $priceRatio,
                    (float)$photoCount,
                    min(1.0, $descLen / 500),
                    0.0,                     // amenity_count — no column on Listing
                    $bedrooms,
                    $bathrooms,
                    $bedrooms * 25.0,        // area proxy: bedrooms × 25 m²
                    $month,
                ];

                // Rule-based days-to-fill label
                $days = 21;
                if ($photoCount === 0)    $days += 18;
                elseif ($photoCount < 3)  $days += 8;
                elseif ($photoCount >= 6) $days -= 5;
                if ($descLen < 100)       $days += 10;
                elseif ($descLen > 300)   $days -= 3;
                if ($priceRatio > 1.2)    $days += 14;
                elseif ($priceRatio < 0.9) $days -= 5;

                $labels[] = (float)max(1, $days);
            } catch (\Throwable) {
                // skip problematic listings
            }
        }

        $result = $this->model->train($samples, $labels);
        $io->success(sprintf('Trained on %d samples', $result['samples']));

        return Command::SUCCESS;
    }

    /**
     * Feature vector (matches ListingPerformanceService::extractFeatures()):
     *   [price_ratio, photo_count, desc_length_ratio, amenity_count, bedrooms, bathrooms, area, month]
     *
     * Label: days until listing is filled (float)
     */
    private function generateSyntheticData(): array
    {
        $samples = [];
        $labels  = [];

        // ── Well-optimised listings — fill quickly ────────────────
        for ($i = 0; $i < 120; $i++) {
            $priceRatio = 0.75 + rand(0, 25) / 100;  // slightly below/at median
            $photos     = rand(5, 12);
            $descRatio  = 0.65 + rand(0, 35) / 100;
            $amenities  = rand(3, 8);
            $beds       = rand(1, 3);
            $baths      = rand(1, 2);
            $area       = $beds * rand(20, 35);
            $month      = rand(8, 10); // peak months

            $days = max(3.0, 10 + (1 - $priceRatio) * 15 - ($photos / 2) - ($descRatio * 8) - ($amenities * 0.5) + rand(-3, 3));

            $samples[] = [(float)$priceRatio, (float)$photos, (float)$descRatio, (float)$amenities, (float)$beds, (float)$baths, (float)$area, (float)$month];
            $labels[]  = $days;
        }

        // ── Average listings ─────────────────────────────────────
        for ($i = 0; $i < 120; $i++) {
            $priceRatio = 0.95 + rand(-15, 25) / 100;
            $photos     = rand(2, 6);
            $descRatio  = 0.35 + rand(0, 40) / 100;
            $amenities  = rand(1, 4);
            $beds       = rand(1, 4);
            $baths      = rand(1, 2);
            $area       = $beds * rand(18, 30);
            $month      = rand(1, 12);

            $days = max(5.0, 21 + ($priceRatio - 1.0) * 20 - ($photos * 1.5) - ($descRatio * 6) + rand(-4, 6));

            $samples[] = [(float)$priceRatio, (float)$photos, (float)$descRatio, (float)$amenities, (float)$beds, (float)$baths, (float)$area, (float)$month];
            $labels[]  = $days;
        }

        // ── Poorly-optimised / overpriced — fill slowly ───────────
        for ($i = 0; $i < 110; $i++) {
            $priceRatio = 1.2 + rand(0, 40) / 100;
            $photos     = rand(0, 2);
            $descRatio  = rand(0, 20) / 100;
            $amenities  = rand(0, 2);
            $beds       = rand(1, 5);
            $baths      = rand(1, 3);
            $area       = $beds * rand(15, 28);
            $month      = rand(6, 8); // low season

            $days = max(15.0, 40 + ($priceRatio - 1.0) * 25 + ($photos === 0 ? 18 : 0) + (1 - $descRatio) * 10 + rand(-3, 8));

            $samples[] = [(float)$priceRatio, (float)$photos, (float)$descRatio, (float)$amenities, (float)$beds, (float)$baths, (float)$area, (float)$month];
            $labels[]  = $days;
        }

        return [$samples, $labels];
    }
}
