<?php

namespace App\Service;

use App\Entity\RoommatePreference;
use App\Entity\User;
use App\Repository\RoommatePreferenceRepository;

class RoommateMatchingService
{
    public function __construct(
        private RoommatePreferenceRepository $preferenceRepository
    ) {}

    public function findMatches(User $user, RoommatePreference $userPref): array
    {
        $allPreferences = $this->preferenceRepository->findAllExceptUser($user);
        $matches = [];

        foreach ($allPreferences as $pref) {
            $score = $this->calculateCompatibility($userPref, $pref);
            if ($score > 0 && $pref->getUser() !== null) {
                $matches[] = [
                    'user'       => $pref->getUser(),
                    'preference' => $pref,
                    'score'      => $score,
                ];
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return $matches;
    }

    public function calculateCompatibility(RoommatePreference $a, RoommatePreference $b): int
    {
        $score = 0;

        // Budget overlap +25
        if ($a->getBudgetMin() !== null && $a->getBudgetMax() !== null
            && $b->getBudgetMin() !== null && $b->getBudgetMax() !== null) {
            $overlapMin = max((float)$a->getBudgetMin(), (float)$b->getBudgetMin());
            $overlapMax = min((float)$a->getBudgetMax(), (float)$b->getBudgetMax());
            if ($overlapMax >= $overlapMin) {
                $score += 25;
            }
        } elseif ($a->getBudgetMin() === null && $b->getBudgetMin() === null) {
            $score += 25;
        }

        // Cleanliness match +15 (within 1 level)
        if ($a->getCleanlinessLevel() !== null && $b->getCleanlinessLevel() !== null) {
            if (abs($a->getCleanlinessLevel() - $b->getCleanlinessLevel()) <= 1) {
                $score += 15;
            }
        } elseif ($a->getCleanlinessLevel() === null && $b->getCleanlinessLevel() === null) {
            $score += 15;
        }

        // Smoking match +15
        if ($a->getSmokingPreference() === $b->getSmokingPreference()) {
            $score += 15;
        }

        // Noise tolerance +10
        if ($a->getNoiseTolerance() === $b->getNoiseTolerance()) {
            $score += 10;
        }

        // Sleep schedule +15
        if ($a->getSleepSchedule() === $b->getSleepSchedule()) {
            $score += 15;
        }

        // Gender preference +10
        $genderA = $a->getGenderPreference();
        $genderB = $b->getGenderPreference();
        if ($genderA === null || $genderA === 'any' || $genderB === null || $genderB === 'any' || $genderA === $genderB) {
            $score += 10;
        }

        // Guest preference +10
        if ($a->getGuests() === $b->getGuests()) {
            $score += 10;
        }

        return min(100, $score);
    }
}
