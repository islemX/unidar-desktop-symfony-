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

        // Helper: compatible when either side has no preference (null or "no_preference"),
        // or both sides picked the same value.
        $compatible = static function (?string $x, ?string $y): bool {
            $isWildcard = static fn(?string $v): bool => $v === null || $v === 'no_preference' || $v === 'any';
            return $isWildcard($x) || $isWildcard($y) || $x === $y;
        };

        // Budget overlap +25 (wildcard if either side left it blank)
        if ($a->getBudgetMin() !== null && $a->getBudgetMax() !== null
            && $b->getBudgetMin() !== null && $b->getBudgetMax() !== null) {
            $overlapMin = max((float)$a->getBudgetMin(), (float)$b->getBudgetMin());
            $overlapMax = min((float)$a->getBudgetMax(), (float)$b->getBudgetMax());
            if ($overlapMax >= $overlapMin) {
                $score += 25;
            }
        } else {
            $score += 25; // no budget set on one side → treat as compatible
        }

        // Cleanliness match +15 (within 1 level; wildcard if either side null)
        if ($a->getCleanlinessLevel() !== null && $b->getCleanlinessLevel() !== null) {
            if (abs($a->getCleanlinessLevel() - $b->getCleanlinessLevel()) <= 1) {
                $score += 15;
            }
        } else {
            $score += 15;
        }

        // Smoking +15
        if ($compatible($a->getSmokingPreference(), $b->getSmokingPreference())) {
            $score += 15;
        }

        // Noise tolerance +10
        if ($compatible($a->getNoiseTolerance(), $b->getNoiseTolerance())) {
            $score += 10;
        }

        // Sleep schedule +15
        if ($compatible($a->getSleepSchedule(), $b->getSleepSchedule())) {
            $score += 15;
        }

        // Gender preference +10
        if ($compatible($a->getGenderPreference(), $b->getGenderPreference())) {
            $score += 10;
        }

        // Guest policy +10
        if ($compatible($a->getGuests(), $b->getGuests())) {
            $score += 10;
        }

        return min(100, $score);
    }
}
