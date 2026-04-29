<?php

namespace App\Controller\Admin;

use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use App\Service\AI\BehaviouralAnomalyService;
use App\Service\AI\ChurnPredictionService;
use App\Service\AI\FraudDetectionService;
use App\Service\AI\LlmService;
use App\Service\AI\ReviewSentimentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai', name: 'admin_ai_')]
#[IsGranted('ROLE_ADMIN')]
class AiDashboardController extends AbstractController
{
    public function __construct(
        private readonly ModelRepository       $modelRepo,
        private readonly LlmService            $llm,
        private readonly FraudDetectionService $fraud,
        private readonly ChurnPredictionService $churn,
        private readonly BehaviouralAnomalyService $anomaly,
        private readonly ReviewSentimentService $sentiment,
        private readonly ListingRepository     $listingRepo,
        private readonly UserRepository        $userRepo,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $models = $this->getModelStatuses();
        $alerts = $this->getActiveAlerts();

        return $this->render('admin/ai_dashboard.html.twig', [
            'models'      => $models,
            'alerts'      => $alerts,
            'llm_online'  => $this->llm->isAvailable(),
            'stats'       => $this->getStats(),
        ]);
    }

    #[Route('/fraud-queue', name: 'fraud_queue')]
    public function fraudQueue(): Response
    {
        $listings = $this->listingRepo->findBy(['status' => 'pending'], null, 50);
        $scored   = [];

        foreach ($listings as $listing) {
            $score    = $this->fraud->score($listing);
            if ($score['risk'] !== 'low') {
                $scored[] = ['listing' => $listing, 'fraud' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['fraud']['score'] <=> $a['fraud']['score']);

        return $this->render('admin/ai_fraud_queue.html.twig', ['items' => $scored]);
    }

    #[Route('/churn-report', name: 'churn_report')]
    public function churnReport(): Response
    {
        $users    = $this->userRepo->findInactiveUsers(30);
        $atRisk   = [];

        foreach (array_slice($users, 0, 100) as $user) {
            $pred = $this->churn->predict($user);
            if ($pred['risk'] !== 'low') {
                $atRisk[] = ['user' => $user, 'prediction' => $pred];
            }
        }

        usort($atRisk, fn($a, $b) => $b['prediction']['churn_percent'] <=> $a['prediction']['churn_percent']);

        return $this->render('admin/ai_churn_report.html.twig', ['users' => array_slice($atRisk, 0, 50)]);
    }

    #[Route('/anomaly-report', name: 'anomaly_report')]
    public function anomalyReport(): Response
    {
        $users      = $this->userRepo->findRecentUsers(7);
        $suspicious = [];

        foreach ($users as $user) {
            $result = $this->anomaly->analyzeUser($user);
            if ($result['risk_level'] !== 'normal') {
                $suspicious[] = ['user' => $user, 'anomaly' => $result];
            }
        }

        usort($suspicious, fn($a, $b) => $b['anomaly']['risk_score'] <=> $a['anomaly']['risk_score']);

        return $this->render('admin/ai_anomaly_report.html.twig', ['items' => $suspicious]);
    }

    #[Route('/sentiment-overview', name: 'sentiment_overview')]
    public function sentimentOverview(Request $request): Response
    {
        // In a real app, fetch reviews from DB; here we use a placeholder
        $reviews = $this->getRecentReviews();
        $analysis = $this->sentiment->analyzeSet($reviews);

        return $this->render('admin/ai_sentiment.html.twig', ['analysis' => $analysis]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getModelStatuses(): array
    {
        $modelNames = [
            'price_recommendation', 'roommate_compatibility', 'listing_quality',
            'fraud_detection', 'churn_prediction', 'dynamic_pricing',
            'behavioural_anomaly', 'energy_cost', 'listing_performance',
        ];

        $statuses = [];
        foreach ($modelNames as $name) {
            $exists = $this->modelRepo->exists($name);
            $meta   = $exists ? $this->modelRepo->getMetadata($name) : [];
            $statuses[$name] = [
                'trained'    => $exists,
                'trained_at' => $meta['trained_at'] ?? null,
                'samples'    => $meta['sample_count'] ?? $meta['samples'] ?? null,
                'label'      => ucwords(str_replace('_', ' ', $name)),
            ];
        }
        return $statuses;
    }

    private function getActiveAlerts(): array
    {
        $alerts = [];

        // Check for high-fraud pending listings
        $pendingCount = $this->listingRepo->count(['status' => 'pending']);
        if ($pendingCount > 0) {
            $alerts[] = ['type' => 'fraud', 'message' => "{$pendingCount} listings pending fraud review", 'url' => '/admin/ai/fraud-queue'];
        }

        // Check for at-risk users
        $inactiveCount = count($this->userRepo->findInactiveUsers(45));
        if ($inactiveCount > 5) {
            $alerts[] = ['type' => 'churn', 'message' => "{$inactiveCount} users at risk of churning", 'url' => '/admin/ai/churn-report'];
        }

        return $alerts;
    }

    private function getStats(): array
    {
        return [
            'total_listings' => $this->listingRepo->count([]),
            'active_listings' => $this->listingRepo->count(['status' => 'active']),
            'total_users'    => $this->userRepo->count([]),
        ];
    }

    private function getRecentReviews(): array
    {
        // Placeholder — wire to your Review entity/repository
        return [];
    }
}
