<?php

namespace App\MessageHandler\AI;

use App\Message\AI\BulkScoreListingsMessage;
use App\Repository\ListingRepository;
use App\Service\AI\ListingOptimizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class BulkScoreListingsHandler
{
    public function __construct(
        private readonly ListingOptimizationService $optimizationService,
        private readonly ListingRepository $listingRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(BulkScoreListingsMessage $message): void
    {
        if (!empty($message->listingIds)) {
            $listings = array_filter(
                array_map(fn($id) => $this->listingRepository->find($id), $message->listingIds)
            );
        } else {
            $listings = $this->listingRepository->findBy(['status' => 'active'], null, 500);
        }

        $scored = 0;
        foreach ($listings as $listing) {
            try {
                $this->optimizationService->scoreListingQuality($listing);
                $scored++;
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to score listing ' . $listing->getId() . ': ' . $e->getMessage());
            }
        }

        $this->logger->info("BulkScoreListings: scored {$scored} listings.");
    }
}
