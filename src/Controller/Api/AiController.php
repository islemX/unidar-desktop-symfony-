<?php

namespace App\Controller\Api;

use App\Entity\Listing;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use App\Service\AI\BehaviouralAnomalyService;
use App\Service\AI\ChurnPredictionService;
use App\Service\AI\CommuteScoreService;
use App\Service\AI\CostOfLivingService;
use App\Service\AI\DisputeMediatorService;
use App\Service\AI\DocumentVerificationService;
use App\Service\AI\DynamicPricingService;
use App\Service\AI\EnergyCostEstimatorService;
use App\Service\AI\FraudDetectionService;
use App\Service\AI\LeaseRiskAnalyzerService;
use App\Service\AI\ListingDescriptionGeneratorService;
use App\Service\AI\ListingPerformanceService;
use App\Service\AI\NaturalLanguageSearchService;
use App\Service\AI\NeighbourhoodVibeService;
use App\Service\AI\PhotoQualityService;
use App\Service\AI\ReviewSentimentService;
use App\Service\AI\RoommateChemistryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ai', name: 'api_ai_')]
class AiController extends AbstractController
{
    public function __construct(
        private readonly NaturalLanguageSearchService      $nlSearch,
        private readonly ListingDescriptionGeneratorService $descGen,
        private readonly PhotoQualityService               $photoQuality,
        private readonly FraudDetectionService             $fraud,
        private readonly EnergyCostEstimatorService        $energy,
        private readonly RoommateChemistryService          $chemistry,
        private readonly LeaseRiskAnalyzerService          $leaseRisk,
        private readonly DisputeMediatorService            $dispute,
        private readonly DynamicPricingService             $dynPricing,
        private readonly CostOfLivingService               $costOfLiving,
        private readonly DocumentVerificationService       $docVerify,
        private readonly BehaviouralAnomalyService         $anomaly,
        private readonly ListingPerformanceService         $performance,
        private readonly ChurnPredictionService            $churn,
        private readonly ReviewSentimentService            $sentiment,
        private readonly CommuteScoreService               $commute,
        private readonly NeighbourhoodVibeService          $vibe,
        private readonly ListingRepository                 $listingRepo,
        private readonly UserRepository                    $userRepo,
    ) {}

    // ── 1. Natural Language Search ─────────────────────────────────────────────
    #[Route('/search', name: 'search', methods: ['GET', 'POST'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q') ?? $request->toArray()['q'] ?? '';
        if (empty($query)) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }

        $result  = $this->nlSearch->search($query, (int) $request->get('limit', 20));
        $listings = $result['listings'] ?? [];
        $scores   = $result['scores']   ?? [];

        $serialized = array_map(function (Listing $l) use ($scores) {
            $data = $this->serializeListingFull($l);
            $data['score'] = isset($scores[$l->getId()])
                ? round($scores[$l->getId()] / 100, 3)
                : null;
            return $data;
        }, $listings);

        return $this->json([
            'query'   => $query,
            'intent'  => $result['parsed'] ?? [],
            'count'   => count($serialized),
            'results' => $serialized,
        ]);
    }

    // ── 4. Listing Description Generator ──────────────────────────────────────
    #[Route('/listing/{id}/description', name: 'description', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateDescription(int $id, Request $request): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        $body = $request->getContent() ? ($request->toArray() ?: []) : [];
        $lang = $request->get('lang') ?? $body['lang'] ?? 'en';
        $all  = $request->get('all')  ?? $body['all']  ?? false;

        try {
            $result = $all
                ? $this->descGen->generateAll($listing)
                : $this->descGen->generate($listing, $lang);
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage(), 'description' => ''], 500);
        }
    }

    // ── 5. Photo Quality Scorer ────────────────────────────────────────────────
    #[Route('/listing/{id}/photo-quality', name: 'photo_quality', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function photoQuality(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        $paths = [];
        foreach ($listing->getImages() as $img) {
            $ip = $img->getImagePath();
            if ($ip && !str_starts_with($ip, 'http')) {
                $paths[] = $this->getParameter('kernel.project_dir') . '/public/uploads/listings/' . $ip;
            }
        }

        return $this->json($this->photoQuality->analyzeSet($paths));
    }

    // ── 6. Fraud Detection ─────────────────────────────────────────────────────
    #[Route('/listing/{id}/fraud-score', name: 'fraud_score', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function fraudScore(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        // Allow admin, or the listing owner to see their own fraud report
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $listing->getOwner() !== $user) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        return $this->json($this->fraud->score($listing));
    }

    // ── 7. Energy Cost Estimator ───────────────────────────────────────────────
    #[Route('/listing/{id}/energy', name: 'energy', methods: ['GET'])]
    public function energyCost(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        try {
            return $this->json($this->energy->estimate($listing));
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not estimate energy costs: ' . $e->getMessage()], 500);
        }
    }

    // ── 8. Roommate Chemistry ──────────────────────────────────────────────────
    #[Route('/roommate/chemistry', name: 'roommate_chemistry', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function roommateChemistry(Request $request): JsonResponse
    {
        $data  = $request->toArray();
        $userA = $this->userRepo->find($data['user_a'] ?? 0);
        $userB = $this->userRepo->find($data['user_b'] ?? 0);

        if (!$userA || !$userB) return $this->json(['error' => 'User(s) not found'], 404);

        return $this->json($this->chemistry->analyze($userA, $userB));
    }

    // ── 10. Lease Risk Analyzer ────────────────────────────────────────────────
    #[Route('/lease/analyze', name: 'lease_analyze', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function leaseAnalyze(Request $request): JsonResponse
    {
        $text = $request->toArray()['text'] ?? $request->request->get('text') ?? '';
        if (empty($text)) {
            return $this->json(['error' => 'Lease text is required'], 400);
        }

        return $this->json($this->leaseRisk->analyze($text));
    }

    // ── 11. Dispute Mediator ───────────────────────────────────────────────────
    #[Route('/dispute/mediate', name: 'dispute_mediate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function disputeMediate(Request $request): JsonResponse
    {
        $data = $request->toArray();
        return $this->json($this->dispute->mediate(
            $data['tenant_statement']  ?? '',
            $data['owner_statement']   ?? '',
            $data['contract_excerpt']  ?? '',
            $data['dispute_type']      ?? 'other',
        ));
    }

    // ── 12. Dynamic Pricing ────────────────────────────────────────────────────
    #[Route('/listing/{id}/dynamic-pricing', name: 'dynamic_pricing', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function dynamicPricing(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        return $this->json($this->dynPricing->recommend($listing));
    }

    // ── 13. Cost of Living ─────────────────────────────────────────────────────
    #[Route('/listing/{id}/cost-of-living', name: 'cost_of_living', methods: ['GET', 'POST'])]
    public function costOfLiving(int $id, Request $request): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        $opts = $request->toArray() ?: [];
        $opts['university'] = $request->get('university') ?? $opts['university'] ?? null;

        return $this->json($this->costOfLiving->estimate($listing, $opts));
    }

    // ── 14. Document Verification ──────────────────────────────────────────────
    #[Route('/document/verify', name: 'doc_verify', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function verifyDocument(Request $request): JsonResponse
    {
        $file    = $request->files->get('document');
        $docType = $request->get('type', 'student_id');

        if (!$file) return $this->json(['error' => 'Document file required'], 400);

        $path = sys_get_temp_dir() . '/' . uniqid('doc_') . '.' . $file->guessExtension();
        $file->move(dirname($path), basename($path));

        $result = $this->docVerify->verify($path, $docType);
        @unlink($path);

        return $this->json($result);
    }

    // ── 15. Behavioural Anomaly ────────────────────────────────────────────────
    #[Route('/user/{id}/anomaly', name: 'user_anomaly', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userAnomaly(int $id): JsonResponse
    {
        $user = $this->userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        return $this->json($this->anomaly->analyzeUser($user));
    }

    // ── 16. Listing Performance ────────────────────────────────────────────────
    #[Route('/listing/{id}/performance', name: 'listing_performance', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listingPerformance(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        return $this->json($this->performance->predict($listing));
    }

    // ── 17. Churn Prediction ───────────────────────────────────────────────────
    #[Route('/user/{id}/churn', name: 'user_churn', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function userChurn(int $id): JsonResponse
    {
        $user = $this->userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        // Users can only see their own churn score unless admin
        if (!$this->isGranted('ROLE_ADMIN') && $user !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        return $this->json($this->churn->predict($user));
    }

    // ── User Cost of Living (dashboard) ───────────────────────────────────────
    #[Route('/user/{id}/cost-of-living', name: 'user_col', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function userCostOfLiving(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);

        if (!$this->isGranted('ROLE_ADMIN') && $user !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Use first saved listing for context, or fall back to city-based estimate
        $savedListings = $user->getSavedListings();
        if ($savedListings && count($savedListings) > 0) {
            $savedListing = $savedListings->first();
            // getSavedListings() returns SavedListing entities — must call getListing()
            $listing = $savedListing ? $savedListing->getListing() : null;
            if ($listing) {
                try {
                    return $this->json($this->costOfLiving->estimate($listing, []));
                } catch (\Throwable $e) {
                    // fall through to generic estimate
                }
            }
        }

        // Generic Tunis estimate when no saved listings or estimation fails
        return $this->json([
            'city'          => 'Tunis',
            'total_monthly' => 950,
            'breakdown'     => [
                'rent'       => 600,
                'utilities'  => 90,
                'internet'   => 40,
                'transport'  => 80,
                'groceries'  => 100,
                'extras'     => 40,
            ],
        ]);
    }

    // ── Admin: Fraud Queue Count ───────────────────────────────────────────────
    #[Route('/admin/fraud-queue-count', name: 'admin_fraud_count', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminFraudQueueCount(): JsonResponse
    {
        $listings = $this->listingRepo->findAll();
        $flagged  = 0;

        foreach (array_slice($listings, 0, 50) as $listing) {
            try {
                $result = $this->fraud->score($listing);
                if (($result['fraud_score'] ?? 0) >= 50) {
                    $flagged++;
                }
            } catch (\Throwable) {}
        }

        return $this->json(['count' => $flagged]);
    }

    // ── Admin: Churn Risk Count ────────────────────────────────────────────────
    #[Route('/admin/churn-risk-count', name: 'admin_churn_count', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminChurnRiskCount(): JsonResponse
    {
        $users   = $this->userRepo->findAll();
        $atRisk  = 0;

        foreach (array_slice($users, 0, 100) as $user) {
            try {
                $result = $this->churn->predict($user);
                if (($result['risk_score'] ?? 0) >= 50) {
                    $atRisk++;
                }
            } catch (\Throwable) {}
        }

        return $this->json(['count' => $atRisk]);
    }

    // ── 18. Review Sentiment ───────────────────────────────────────────────────
    #[Route('/reviews/sentiment', name: 'review_sentiment', methods: ['POST'])]
    public function reviewSentiment(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (isset($data['text'])) {
            return $this->json($this->sentiment->analyzeReview($data['text']));
        }
        if (isset($data['reviews'])) {
            return $this->json($this->sentiment->analyzeSet($data['reviews']));
        }
        return $this->json(['error' => 'Provide "text" or "reviews" array'], 400);
    }

    // ── 19. Commute Score ──────────────────────────────────────────────────────
    #[Route('/listing/{id}/commute', name: 'commute', methods: ['GET'])]
    public function commuteScore(int $id, Request $request): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        return $this->json($this->commute->calculate($listing, $request->get('university')));
    }

    // ── 20. Neighbourhood Vibe ─────────────────────────────────────────────────
    #[Route('/listing/{id}/neighbourhood', name: 'neighbourhood', methods: ['GET'])]
    public function neighbourhood(int $id): JsonResponse
    {
        $listing = $this->listingRepo->find($id);
        if (!$listing) return $this->json(['error' => 'Listing not found'], 404);

        return $this->json($this->vibe->analyze($listing));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function serializeListing(Listing $l): array
    {
        return [
            'id'           => $l->getId(),
            'title'        => $l->getTitle(),
            'price'        => $l->getPrice(),
            'city'         => $l->getCity(),
            'bedrooms'     => $l->getBedrooms(),
            'property_type'=> $l->getPropertyType()?->value ?? '',
            'area'         => $l->getArea(),
        ];
    }

    private function serializeListingFull(mixed $l): array
    {
        // $l may be a Listing entity OR an array from NL search with score
        $score  = null;
        if (is_array($l)) {
            $score   = $l['score'] ?? $l['similarity'] ?? null;
            $listing = $l['listing'] ?? null;
            if (!$listing instanceof Listing) {
                return $l; // already serialized
            }
            $l = $listing;
        }

        $firstImg = null;
        if ($l->getImages() && count($l->getImages()) > 0) {
            $ip = $l->getImages()->first()->getImagePath();
            $firstImg = str_starts_with($ip, 'http') ? $ip : '/uploads/listings/' . $ip;
        }

        return array_filter([
            'id'            => $l->getId(),
            'title'         => $l->getTitle(),
            'price'         => $l->getPrice(),
            'address'       => $l->getAddress(),
            'city'          => $l->getCity(),
            'bedrooms'      => $l->getBedrooms(),
            'property_type' => $l->getPropertyType()?->value ?? '',
            'area'          => $l->getArea(),
            'image'         => $firstImg,
            'score'         => $score,
            'url'           => '/listing/' . $l->getId(),
        ], fn($v) => $v !== null);
    }
}
