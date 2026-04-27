<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use App\Service\AI\RoommateAiMatchingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class RoommateMatchApiController extends AbstractController
{
    public function __construct(
        private readonly RoommateAiMatchingService $matchingService,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * GET /api/users/{id}/roommate-matches
     * Returns AI-ranked list of compatible roommates for a student.
     */
    #[Route('/users/{id}/roommate-matches', name: 'api_roommate_matches', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function matches(int $id, Request $request): JsonResponse
    {
        $student = $this->userRepository->find($id);

        if (!$student) {
            return $this->json(['error' => 'User not found.'], 404);
        }

        // Users may only query their own matches
        if ($student !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], 403);
        }

        $limit = min(20, max(1, (int) $request->query->get('limit', 5)));
        $matches = $this->matchingService->findBestMatches($student, $limit);

        $data = array_map(fn($match) => [
            'user' => [
                'id' => $match['user']->getId(),
                'full_name' => $match['user']->getFullName(),
                'university' => $match['user']->getUniversity(),
                'gender' => $match['user']->getGender(),
            ],
            'compatibility_score' => $match['compatibility_score'],
            'matching_traits' => $match['matching_traits'],
            'differences' => $match['differences'],
        ], $matches);

        return $this->json([
            'student_id' => $id,
            'matches' => $data,
            'model_ready' => $this->matchingService->isModelReady(),
            'total' => count($data),
        ]);
    }

    /**
     * GET /api/roommate-matches (current user)
     */
    #[Route('/roommate-matches', name: 'api_my_roommate_matches', methods: ['GET'])]
    #[IsGranted('ROLE_STUDENT')]
    public function myMatches(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $limit = min(20, max(1, (int) $request->query->get('limit', 5)));
        $matches = $this->matchingService->findBestMatches($user, $limit);

        $data = array_map(fn($match) => [
            'user' => [
                'id' => $match['user']->getId(),
                'full_name' => $match['user']->getFullName(),
                'university' => $match['user']->getUniversity(),
                'gender' => $match['user']->getGender(),
            ],
            'compatibility_score' => $match['compatibility_score'],
            'matching_traits' => $match['matching_traits'],
            'differences' => $match['differences'],
        ], $matches);

        return $this->json([
            'matches' => $data,
            'model_ready' => $this->matchingService->isModelReady(),
            'total' => count($data),
        ]);
    }

    /**
     * GET /api/ai/models/roommate/info
     */
    #[Route('/ai/models/roommate/info', name: 'api_roommate_model_info', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function modelInfo(): JsonResponse
    {
        return $this->json([
            'model' => 'roommate_compatibility',
            'ready' => $this->matchingService->isModelReady(),
            'metadata' => $this->matchingService->getModelInfo(),
        ]);
    }
}
