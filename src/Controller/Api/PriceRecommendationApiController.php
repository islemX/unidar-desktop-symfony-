<?php

namespace App\Controller\Api;

use App\Repository\ListingRepository;
use App\Service\AI\PriceRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class PriceRecommendationApiController extends AbstractController
{
    public function __construct(
        private readonly PriceRecommendationService $priceService,
        private readonly ListingRepository $listingRepository,
    ) {}

    /**
     * GET /api/listings/{id}/price-suggestion
     * Returns AI-driven price recommendation for a listing.
     */
    #[Route('/listings/{id}/price-suggestion', name: 'api_listing_price_suggestion', methods: ['GET'])]
    #[IsGranted('ROLE_OWNER')]
    public function suggest(int $id): JsonResponse
    {
        $listing = $this->listingRepository->find($id);

        if (!$listing) {
            return $this->json(['error' => 'Listing not found.'], 404);
        }

        if ($listing->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], 403);
        }

        $recommendation = $this->priceService->recommendPrice($listing);

        return $this->json([
            'listing_id' => $id,
            'current_price' => $recommendation['current_price'],
            'suggested_price' => $recommendation['suggested_price'],
            'range' => [
                'min' => $recommendation['min'],
                'max' => $recommendation['max'],
            ],
            'delta' => $recommendation['delta'],
            'rationale' => $recommendation['rationale'],
            'comparable_count' => $recommendation['comparable_count'],
            'model_ready' => $this->priceService->isModelReady(),
        ]);
    }

    /**
     * GET /api/ai/models/price/info
     */
    #[Route('/ai/models/price/info', name: 'api_price_model_info', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function modelInfo(): JsonResponse
    {
        return $this->json([
            'model' => 'price_recommendation',
            'ready' => $this->priceService->isModelReady(),
            'metadata' => $this->priceService->getModelInfo(),
        ]);
    }
}
