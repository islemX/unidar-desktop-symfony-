<?php

namespace App\Controller\Api;

use App\Repository\ListingRepository;
use App\Service\AI\ListingOptimizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ListingOptimizationApiController extends AbstractController
{
    public function __construct(
        private readonly ListingOptimizationService $optimizationService,
        private readonly ListingRepository $listingRepository,
    ) {}

    /**
     * GET /api/listings/{id}/optimization-score
     * Returns AI-generated quality score and improvement suggestions for a listing.
     */
    #[Route('/listings/{id}/optimization-score', name: 'api_listing_optimization_score', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function score(int $id): JsonResponse
    {
        $listing = $this->listingRepository->find($id);

        if (!$listing) {
            return $this->json(['error' => 'Listing not found.'], 404);
        }

        $report = $this->optimizationService->scoreListingQuality($listing);

        return $this->json([
            'listing_id' => $id,
            'quality_score' => $report['score'],
            'tier' => $report['tier'],
            'issues' => $report['issues'],
            'suggestions' => $report['suggestions'],
            'impact_estimate' => $report['impact_estimate'],
            'model_ready' => $this->optimizationService->isModelReady(),
        ]);
    }

    /**
     * GET /api/ai/models/listing-quality/info
     * Returns metadata about the trained model.
     */
    #[Route('/ai/models/listing-quality/info', name: 'api_listing_quality_model_info', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function modelInfo(): JsonResponse
    {
        return $this->json([
            'model' => 'listing_quality',
            'ready' => $this->optimizationService->isModelReady(),
            'metadata' => $this->optimizationService->getModelInfo(),
        ]);
    }
}
