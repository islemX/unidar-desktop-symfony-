<?php

namespace App\MessageHandler\AI;

use App\Message\AI\TrainModelMessage;
use App\Ml\Model\ListingQualityModel;
use App\Ml\Model\PriceRecommendationModel;
use App\Ml\Model\RoommateCompatibilityModel;
use App\Ml\Preprocessor\ListingFeatureExtractor;
use App\Ml\Preprocessor\UserFeatureExtractor;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TrainModelHandler
{
    public function __construct(
        private readonly ListingQualityModel $qualityModel,
        private readonly PriceRecommendationModel $priceModel,
        private readonly RoommateCompatibilityModel $roommateModel,
        private readonly ListingRepository $listingRepository,
        private readonly UserRepository $userRepository,
        private readonly ListingFeatureExtractor $listingExtractor,
        private readonly UserFeatureExtractor $userExtractor,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TrainModelMessage $message): void
    {
        $this->logger->info('Starting async model training', ['model' => $message->modelType]);

        match ($message->modelType) {
            'listing_quality' => $this->trainListingQuality($message->useSynthetic),
            'price' => $this->trainPrice($message->useSynthetic),
            'roommate' => $this->trainRoommate($message->useSynthetic),
            default => throw new \InvalidArgumentException("Unknown model type: {$message->modelType}"),
        };

        $this->logger->info('Model training complete', ['model' => $message->modelType]);
    }

    private function trainListingQuality(bool $useSynthetic): void
    {
        $listings = $this->listingRepository->findBy(['status' => 'active'], null, 500);
        $samples = $labels = [];

        foreach ($listings as $listing) {
            $features = $this->listingExtractor->extract($listing);
            $photoCount = $listing->getImages()->count();
            $wordCount = str_word_count(strip_tags($listing->getDescription() ?? ''));

            $samples[] = $features;
            $labels[] = match(true) {
                $photoCount >= 8 && $wordCount >= 100 => 'excellent',
                $photoCount >= 4 && $wordCount >= 50 => 'good',
                default => 'fair',
            };
        }

        if (count($samples) < 30 || $useSynthetic) {
            $synthetic = ListingQualityModel::generateSyntheticSamples();
            $samples = array_merge($samples, $synthetic['samples']);
            $labels = array_merge($labels, $synthetic['labels']);
        }

        $this->qualityModel->train($samples, $labels);
    }

    private function trainPrice(bool $useSynthetic): void
    {
        $listings = $this->listingRepository->findBy(['status' => 'active'], null, 1000);
        $samples = $prices = [];

        foreach ($listings as $listing) {
            $price = (float) $listing->getPrice();
            if ($price <= 0) {
                continue;
            }
            $samples[] = $this->listingExtractor->extract($listing);
            $prices[] = $price;
        }

        if (count($samples) < 30 || $useSynthetic) {
            $synthetic = PriceRecommendationModel::generateSyntheticSamples();
            $samples = array_merge($samples, $synthetic['samples']);
            $prices = array_merge($prices, $synthetic['prices']);
        }

        $this->priceModel->train($samples, $prices);
    }

    private function trainRoommate(bool $useSynthetic): void
    {
        $students = $this->userRepository->findBy(['role' => UserRole::Student], null, 200);
        $samples = $labels = [];

        foreach ($students as $studentA) {
            if (!$studentA->getRoommatePreference()) {
                continue;
            }
            foreach ($students as $studentB) {
                if ($studentA->getId() === $studentB->getId() || !$studentB->getRoommatePreference()) {
                    continue;
                }
                $featA = $this->userExtractor->extract($studentA);
                $featB = $this->userExtractor->extract($studentB);
                $diff = array_map(fn($a, $b) => abs($a - $b), $featA, $featB);
                $samples[] = $diff;
                $labels[] = ($diff[0] <= 1 && $diff[1] <= 0 && $diff[3] <= 0) ? 'compatible' : 'incompatible';

                if (count($samples) >= 200) {
                    break 2;
                }
            }
        }

        if (count($samples) < 20 || $useSynthetic) {
            $synthetic = RoommateCompatibilityModel::generateSyntheticSamples();
            $samples = array_merge($samples, $synthetic['samples']);
            $labels = array_merge($labels, $synthetic['labels']);
        }

        $this->roommateModel->train($samples, $labels);
    }
}
