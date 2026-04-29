<?php

namespace App\Service\AI;

use App\Entity\User;
use App\Ml\Model\RoommateCompatibilityModel;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use App\Enum\UserStatus;

class RoommateAiMatchingService
{
    public function __construct(
        private readonly RoommateCompatibilityModel $model,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Finds and ranks the best roommate matches for a student.
     *
     * @return array{user: User, compatibility_score: int, matching_traits: array, differences: array}[]
     */
    public function findBestMatches(User $student, int $limit = 5): array
    {
        $candidates = $this->loadCandidates($student);

        if (empty($candidates)) {
            return [];
        }

        $ranked = $this->model->rankCandidates($student, $candidates);

        return array_slice($ranked, 0, $limit);
    }

    public function isModelReady(): bool
    {
        return true; // scoring is always available (deterministic weighted algorithm)
    }

    public function getModelInfo(): array
    {
        return ['algorithm' => 'weighted_preference_matching', 'version' => '2.0'];
    }

    private function loadCandidates(User $student): array
    {
        return $this->userRepository->findBy([
            'role' => UserRole::Student,
            'status' => UserStatus::Active,
        ], null, 200);
    }
}
