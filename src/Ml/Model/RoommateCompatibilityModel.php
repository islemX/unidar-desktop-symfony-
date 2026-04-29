<?php

namespace App\Ml\Model;

use App\Entity\User;
use App\Ml\Preprocessor\UserFeatureExtractor;
use App\Ml\Storage\ModelRepository;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Kernels\Distance\Euclidean;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ZScaleStandardizer;
use Rubix\ML\CrossValidation\Metrics\Accuracy;
use Rubix\ML\CrossValidation\KFold;

class RoommateCompatibilityModel
{
    public const MODEL_NAME = 'roommate_compatibility';

    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly UserFeatureExtractor $extractor,
    ) {}

    /**
     * @param array $samples Feature vectors (user preference vectors)
     * @param string[] $labels 'compatible'|'incompatible'
     */
    public function train(array $samples, array $labels): array
    {
        $dataset = new Labeled($samples, $labels);

        $estimator = new Pipeline([
            new ZScaleStandardizer(),
        ], new KNearestNeighbors(5, true, new Euclidean()));

        $validator = new KFold(5);
        $metric = new Accuracy();
        $score = $validator->test($estimator, $dataset, $metric);

        $estimator->train($dataset);

        $this->modelRepository->save($estimator, self::MODEL_NAME);
        $this->modelRepository->saveMetadata(self::MODEL_NAME, [
            'cross_val_accuracy' => round($score, 4),
            'sample_count' => count($samples),
        ]);

        return ['cross_val_accuracy' => $score, 'samples' => count($samples)];
    }

    /**
     * Computes a weighted preference similarity score between two users.
     * Returns a float 0.0–1.0.
     *
     * Weights (total = 1.0):
     *   sleep_schedule   0.25  — biggest daily routine conflict
     *   cleanliness      0.20  — most common roommate friction source
     *   smoking          0.20  — lifestyle, health, hard incompatibility
     *   noise_tolerance  0.15  — study/relaxation compatibility
     *   budget_overlap   0.10  — financial fit
     *   gender_pref      0.05  — stated preference
     *   guests_policy    0.05  — social habits
     */
    public function compatibilityScore(User $userA, User $userB): float
    {
        $prefA = $userA->getRoommatePreference();
        $prefB = $userB->getRoommatePreference();

        if ($prefA === null && $prefB === null) {
            return 0.50; // both unknown → neutral
        }
        if ($prefA === null || $prefB === null) {
            return 0.38; // one side unknown → below neutral
        }

        $score = 0.0;

        // — Sleep schedule (0.25) —
        $schedules = ['early' => 0, 'normal' => 1, 'late' => 2, 'night_owl' => 3];
        $sA = $prefA->getSleepSchedule();
        $sB = $prefB->getSleepSchedule();
        if ($sA === null || $sB === null) {
            $score += 0.25 * 0.50;
        } elseif ($sA === $sB) {
            $score += 0.25;
        } else {
            $diff = abs(($schedules[$sA] ?? 1) - ($schedules[$sB] ?? 1));
            $score += 0.25 * max(0.0, 1.0 - $diff / 3.0);
        }

        // — Cleanliness (0.20) — integer 1-4, scale difference linearly
        $cleanA = $prefA->getCleanlinessLevel() ?? 3;
        $cleanB = $prefB->getCleanlinessLevel() ?? 3;
        $score += 0.20 * (1.0 - abs($cleanA - $cleanB) / 3.0);

        // — Smoking (0.20) — smoker vs non-smoker is a hard incompatibility
        $smkA = $prefA->getSmokingPreference();
        $smkB = $prefB->getSmokingPreference();
        if ($smkA === null || $smkB === null) {
            $score += 0.20 * 0.50;
        } elseif ($smkA === $smkB) {
            $score += 0.20;
        } elseif (
            ($smkA === 'non_smoker' && $smkB === 'smoker') ||
            ($smkA === 'smoker' && $smkB === 'non_smoker')
        ) {
            $score += 0.0; // hard incompatibility
        } else {
            $score += 0.20 * 0.60; // one side is flexible (no_preference)
        }

        // — Noise tolerance (0.15) —
        $noises = ['quiet' => 0, 'moderate' => 1, 'loud' => 2];
        $nA = $prefA->getNoiseTolerance();
        $nB = $prefB->getNoiseTolerance();
        if ($nA === null || $nB === null) {
            $score += 0.15 * 0.50;
        } elseif ($nA === $nB) {
            $score += 0.15;
        } else {
            $diff = abs(($noises[$nA] ?? 1) - ($noises[$nB] ?? 1));
            $score += 0.15 * max(0.0, 1.0 - $diff / 2.0);
        }

        // — Budget overlap (0.10) —
        $minA = (float)($prefA->getBudgetMin() ?? 0);
        $maxA = (float)($prefA->getBudgetMax() ?? 9999);
        $minB = (float)($prefB->getBudgetMin() ?? 0);
        $maxB = (float)($prefB->getBudgetMax() ?? 9999);
        if ($minA <= $maxB && $minB <= $maxA) {
            $overlap = min($maxA, $maxB) - max($minA, $minB);
            $span    = max($maxA, $maxB) - min($minA, $minB);
            $ratio   = $span > 0 ? $overlap / $span : 1.0;
            $score  += 0.10 * (0.50 + 0.50 * $ratio);
        }

        // — Gender preference (0.05) —
        $gpA = $prefA->getGenderPreference();
        $gpB = $prefB->getGenderPreference();
        $gA  = $userA->getGender();
        $gB  = $userB->getGender();
        if ($gpA === null || $gpA === 'any' || $gpB === null || $gpB === 'any') {
            $score += 0.05; // no preference = accepts all
        } elseif ($gA !== null && $gB !== null && $gpA === $gB && $gpB === $gA) {
            $score += 0.05; // mutual match
        } elseif ($gA !== null && $gpB === $gA) {
            $score += 0.025; // one-way match
        }

        // — Guests policy (0.05) —
        $guestOrder = ['never' => 0, 'rarely' => 1, 'sometimes' => 2, 'often' => 3];
        $guA = $prefA->getGuests();
        $guB = $prefB->getGuests();
        if ($guA === null || $guB === null) {
            $score += 0.05 * 0.50;
        } elseif ($guA === $guB) {
            $score += 0.05;
        } else {
            $diff = abs(($guestOrder[$guA] ?? 1) - ($guestOrder[$guB] ?? 1));
            $score += 0.05 * max(0.0, 1.0 - $diff / 3.0);
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * For a given user, scores and ranks candidate users by compatibility.
     * Returns array of ['user' => User, 'score' => float, 'traits' => array, 'differences' => array]
     */
    public function rankCandidates(User $subject, array $candidates): array
    {
        $results = [];

        foreach ($candidates as $candidate) {
            if ($candidate->getId() === $subject->getId()) {
                continue;
            }

            $score = $this->compatibilityScore($subject, $candidate);
            $traits = $this->matchingTraits($subject, $candidate);
            $differences = $this->differencingTraits($subject, $candidate);

            $results[] = [
                'user' => $candidate,
                'compatibility_score' => round($score * 100),
                'matching_traits' => $traits,
                'differences' => $differences,
            ];
        }

        usort($results, fn($a, $b) => $b['compatibility_score'] <=> $a['compatibility_score']);

        return $results;
    }

    private function matchingTraits(User $a, User $b): array
    {
        $traits = [];
        $prefA = $a->getRoommatePreference();
        $prefB = $b->getRoommatePreference();

        if (!$prefA || !$prefB) {
            return $traits;
        }

        if ($prefA->getSleepSchedule() !== null && $prefA->getSleepSchedule() === $prefB->getSleepSchedule()) {
            $traits[] = 'Same sleep schedule (' . $prefA->getSleepSchedule() . ')';
        }
        if ($prefA->getSmokingPreference() !== null && $prefA->getSmokingPreference() === $prefB->getSmokingPreference()) {
            $traits[] = 'Same smoking preference';
        } elseif ($prefA->getSmokingPreference() === null && $prefB->getSmokingPreference() === null) {
            $traits[] = 'Both flexible on smoking';
        }
        if (abs(($prefA->getCleanlinessLevel() ?? 3) - ($prefB->getCleanlinessLevel() ?? 3)) <= 1) {
            $traits[] = 'Similar cleanliness standards';
        }
        if ($prefA->getNoiseTolerance() !== null && $prefA->getNoiseTolerance() === $prefB->getNoiseTolerance()) {
            $traits[] = 'Same noise tolerance (' . $prefA->getNoiseTolerance() . ')';
        }

        $minA = (float) ($prefA->getBudgetMin() ?? 0);
        $maxA = (float) ($prefA->getBudgetMax() ?? 9999);
        $minB = (float) ($prefB->getBudgetMin() ?? 0);
        $maxB = (float) ($prefB->getBudgetMax() ?? 9999);
        if ($minA <= $maxB && $minB <= $maxA) {
            $traits[] = 'Overlapping budget range';
        }

        return $traits;
    }

    private function differencingTraits(User $a, User $b): array
    {
        $diffs = [];
        $prefA = $a->getRoommatePreference();
        $prefB = $b->getRoommatePreference();

        if (!$prefA || !$prefB) {
            return $diffs;
        }

        if ($prefA->getSleepSchedule() !== null && $prefB->getSleepSchedule() !== null
            && $prefA->getSleepSchedule() !== $prefB->getSleepSchedule()) {
            $diffs[] = 'Different sleep schedules';
        }
        if (abs(($prefA->getCleanlinessLevel() ?? 3) - ($prefB->getCleanlinessLevel() ?? 3)) >= 3) {
            $diffs[] = 'Very different cleanliness standards';
        }
        if ($prefA->getSmokingPreference() !== null && $prefB->getSmokingPreference() !== null
            && $prefA->getSmokingPreference() !== $prefB->getSmokingPreference()
            && (($prefA->getSmokingPreference() === 'non_smoker' && $prefB->getSmokingPreference() === 'smoker')
               || ($prefA->getSmokingPreference() === 'smoker' && $prefB->getSmokingPreference() === 'non_smoker'))) {
            $diffs[] = 'Conflicting smoking preferences';
        }

        return $diffs;
    }

    public static function generateSyntheticSamples(): array
    {
        $samples = [];
        $labels = [];

        // Compatible pairs: similar cleanliness (3-5), same sleep schedule (0-1)
        for ($i = 0; $i < 50; $i++) {
            $clean = rand(3, 5);
            $sleep = rand(0, 1);
            $noise = rand(0, 1);
            $samples[] = [$clean, $sleep, $noise, 0, 1, 0, rand(300, 600) / 1000, rand(600, 1000) / 1000, 2, 20, 30];
            $labels[] = 'compatible';
        }
        // Incompatible: big gap in cleanliness or opposite smoke/sleep
        for ($i = 0; $i < 50; $i++) {
            $clean = rand(1, 2);
            $sleep = rand(2, 3);
            $samples[] = [$clean, $sleep, 2, 1, 3, 1, rand(100, 200) / 1000, rand(200, 350) / 1000, rand(0, 1), 18, 25];
            $labels[] = 'incompatible';
        }

        return ['samples' => $samples, 'labels' => $labels];
    }
}
