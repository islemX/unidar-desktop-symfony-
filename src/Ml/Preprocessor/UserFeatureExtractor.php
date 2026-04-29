<?php

namespace App\Ml\Preprocessor;

use App\Entity\User;

class UserFeatureExtractor
{
    private const SLEEP_SCHEDULE_MAP = ['early' => 0, 'normal' => 1, 'late' => 2, 'night_owl' => 3];
    private const NOISE_MAP = ['quiet' => 0, 'moderate' => 1, 'loud' => 2];
    private const GUESTS_MAP = ['never' => 0, 'rarely' => 1, 'sometimes' => 2, 'often' => 3];
    private const SMOKING_MAP = ['non_smoker' => 0, 'smoker' => 1, 'no_preference' => 2];
    private const GENDER_MAP = ['male' => 0, 'female' => 1, 'any' => 2];

    /**
     * Extracts roommate preference features from a User entity.
     * Order must remain stable across training and inference.
     *
     * [cleanliness, sleep_schedule_enc, noise_enc, smoking_enc,
     *  guests_enc, pets, budget_min, budget_max, gender_enc, age_min, age_max]
     */
    public function extract(User $user): array
    {
        $pref = $user->getRoommatePreference();

        if ($pref === null) {
            return array_fill(0, 11, 0.0);
        }

        return [
            (float) ($pref->getCleanlinessLevel() ?? 3),
            (float) (self::SLEEP_SCHEDULE_MAP[$pref->getSleepSchedule() ?? ''] ?? 1),
            (float) (self::NOISE_MAP[$pref->getNoiseTolerance() ?? ''] ?? 1),
            (float) (self::SMOKING_MAP[$pref->getSmokingPreference() ?? ''] ?? 2),
            (float) (self::GUESTS_MAP[$pref->getGuests() ?? ''] ?? 1),
            $pref->getPets() !== null && $pref->getPets() !== 'no' ? 1.0 : 0.0,
            (float) ($pref->getBudgetMin() ?? 0),
            (float) ($pref->getBudgetMax() ?? 2000),
            (float) (self::GENDER_MAP[$pref->getGenderPreference() ?? ''] ?? 2),
            (float) ($pref->getAgeMin() ?? 18),
            (float) ($pref->getAgeMax() ?? 35),
        ];
    }

    public function featureNames(): array
    {
        return [
            'cleanliness', 'sleep_schedule_enc', 'noise_enc',
            'smoking_enc', 'guests_enc', 'pets',
            'budget_min', 'budget_max', 'gender_enc', 'age_min', 'age_max',
        ];
    }
}
