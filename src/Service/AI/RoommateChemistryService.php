<?php

namespace App\Service\AI;

use App\Entity\User;

/**
 * Feature 8: Roommate Chemistry Score Timeline
 * Predicts compatibility + conflict heatmap over the academic year.
 */
class RoommateChemistryService
{
    private const EXAM_MONTHS   = [1, 5, 6, 12]; // Jan, May, Jun, Dec
    private const HOLIDAY_MONTHS = [7, 8];        // Summer holidays

    public function __construct() {}

    /**
     * @return array [overall_score, timeline, conflict_periods, strengths, warnings]
     */
    public function analyze(User $userA, User $userB): array
    {
        $profA = $this->buildProfile($userA);
        $profB = $this->buildProfile($userB);

        $dims     = $this->computeDimensions($profA, $profB);
        $overall  = $this->overallScore($dims);
        $timeline = $this->buildTimeline($dims, $profA, $profB);

        return [
            'overall_score'    => $overall,
            'grade'            => $this->grade($overall),
            'dimensions'       => $dims,
            'timeline'         => $timeline,
            'conflict_periods' => $this->identifyConflictPeriods($timeline),
            'strengths'        => $this->strengths($dims),
            'warnings'         => $this->warnings($dims),
            'tips'             => $this->cohabitTips($dims),
        ];
    }

    /**
     * Optimal group assembly for multi-bedroom listings.
     * @param User[]  $candidates
     * @return User[] best group of $size
     */
    public function assembleGroup(array $candidates, int $size): array
    {
        if (count($candidates) <= $size) return $candidates;

        $best      = [];
        $bestScore = -1;

        // Greedy: start with highest-rated pair, then add best additions
        $pairs = [];
        for ($i = 0; $i < count($candidates); $i++) {
            for ($j = $i + 1; $j < count($candidates); $j++) {
                $s = $this->analyze($candidates[$i], $candidates[$j])['overall_score'];
                $pairs[] = ['i' => $i, 'j' => $j, 'score' => $s];
            }
        }
        usort($pairs, fn($a, $b) => $b['score'] <=> $a['score']);

        $group   = [$candidates[$pairs[0]['i']], $candidates[$pairs[0]['j']]];
        $used    = [$pairs[0]['i'], $pairs[0]['j']];

        while (count($group) < $size) {
            $bestAdd = null;
            $bestS   = -1;
            foreach ($candidates as $idx => $c) {
                if (in_array($idx, $used)) continue;
                $groupScore = 0;
                foreach ($group as $g) {
                    $groupScore += $this->analyze($g, $c)['overall_score'];
                }
                $avgScore = $groupScore / count($group);
                if ($avgScore > $bestS) { $bestS = $avgScore; $bestAdd = $idx; }
            }
            if ($bestAdd === null) break;
            $group[] = $candidates[$bestAdd];
            $used[]  = $bestAdd;
        }

        return $group;
    }

    // ── Profile extraction ────────────────────────────────────────────────────

    private function buildProfile(User $user): array
    {
        $prefs = $user->getRoommatePreferences() ?? [];
        return [
            'sleep_schedule'   => $prefs['sleep_schedule']   ?? 'flexible',
            'cleanliness'      => $prefs['cleanliness']      ?? 3,
            'noise_tolerance'  => $prefs['noise_tolerance']  ?? 3,
            'social_level'     => $prefs['social_level']     ?? 3,
            'study_style'      => $prefs['study_style']      ?? 'flexible',
            'smoking'          => $prefs['smoking']          ?? false,
            'pets'             => $prefs['pets']             ?? false,
            'guest_frequency'  => $prefs['guest_frequency']  ?? 'sometimes',
            'field_of_study'   => $user->getFieldOfStudy()   ?? 'general',
            'year_of_study'    => $user->getYearOfStudy()    ?? 1,
        ];
    }

    // ── Dimension scoring ─────────────────────────────────────────────────────

    private function computeDimensions(array $a, array $b): array
    {
        return [
            'sleep_schedule'  => $this->scheduleScore($a['sleep_schedule'], $b['sleep_schedule']),
            'cleanliness'     => $this->numericScore($a['cleanliness'], $b['cleanliness'], 5),
            'noise_tolerance' => $this->numericScore($a['noise_tolerance'], $b['noise_tolerance'], 5),
            'social_level'    => $this->numericScore($a['social_level'], $b['social_level'], 5),
            'lifestyle'       => $this->lifestyleScore($a, $b),
            'study_compat'    => $this->studyScore($a, $b),
        ];
    }

    private function scheduleScore(string $a, string $b): float
    {
        $map = ['early_bird' => 1, 'flexible' => 2, 'night_owl' => 3];
        $diff = abs(($map[$a] ?? 2) - ($map[$b] ?? 2));
        return match ($diff) { 0 => 100.0, 1 => 65.0, default => 30.0 };
    }

    private function numericScore(mixed $a, mixed $b, int $max): float
    {
        $diff = abs((float)$a - (float)$b);
        return max(0.0, 100.0 - ($diff / $max) * 100.0);
    }

    private function lifestyleScore(array $a, array $b): float
    {
        $score = 100.0;
        if ($a['smoking'] !== $b['smoking'])                       $score -= 30;
        if ($a['pets'] !== $b['pets'])                             $score -= 15;
        if ($a['guest_frequency'] !== $b['guest_frequency'])       $score -= 15;
        return max(0.0, $score);
    }

    private function studyScore(array $a, array $b): float
    {
        $score = 100.0;
        if ($a['field_of_study'] === $b['field_of_study'])   $score += 10; // shared context
        $yearDiff = abs((int)$a['year_of_study'] - (int)$b['year_of_study']);
        $score -= $yearDiff * 5;
        return min(100.0, max(0.0, $score));
    }

    private function overallScore(array $dims): int
    {
        $weights = [
            'sleep_schedule'  => 0.30,
            'cleanliness'     => 0.25,
            'noise_tolerance' => 0.15,
            'social_level'    => 0.10,
            'lifestyle'       => 0.15,
            'study_compat'    => 0.05,
        ];

        $score = 0.0;
        foreach ($dims as $key => $val) {
            $score += $val * ($weights[$key] ?? 0);
        }

        return (int) round($score);
    }

    // ── Timeline ──────────────────────────────────────────────────────────────

    private function buildTimeline(array $dims, array $profA, array $profB): array
    {
        $months = [];
        $base   = $this->overallScore($dims);

        for ($m = 1; $m <= 12; $m++) {
            $adj = 0;

            // Exam stress raises conflicts around sleep & noise
            if (in_array($m, self::EXAM_MONTHS)) {
                $adj -= (int) ((100 - $dims['sleep_schedule']) * 0.15);
                $adj -= (int) ((100 - $dims['noise_tolerance']) * 0.10);
            }

            // Summer holidays — social friction if one stays, one leaves
            if (in_array($m, self::HOLIDAY_MONTHS)) {
                $adj += 5; // usually more relaxed
            }

            $score = min(100, max(0, $base + $adj));
            $months[] = [
                'month'      => $m,
                'month_name' => date('F', mktime(0, 0, 0, $m, 1)),
                'score'      => $score,
                'stress'     => in_array($m, self::EXAM_MONTHS) ? 'exam' : (in_array($m, self::HOLIDAY_MONTHS) ? 'holiday' : 'normal'),
            ];
        }

        return $months;
    }

    private function identifyConflictPeriods(array $timeline): array
    {
        return array_values(array_filter($timeline, fn($m) => $m['score'] < 55));
    }

    private function strengths(array $dims): array
    {
        $good = array_filter($dims, fn($v) => $v >= 75);
        arsort($good);
        return array_map(
            fn($k) => ucwords(str_replace('_', ' ', $k)) . ' compatibility',
            array_keys(array_slice($good, 0, 3, true))
        );
    }

    private function warnings(array $dims): array
    {
        $bad = array_filter($dims, fn($v) => $v < 50);
        return array_map(
            fn($k) => 'Potential friction around ' . strtolower(str_replace('_', ' ', $k)),
            array_keys($bad)
        );
    }

    private function cohabitTips(array $dims): array
    {
        $tips = [];
        if ($dims['sleep_schedule'] < 60) $tips[] = 'Agree on quiet hours during the first week';
        if ($dims['cleanliness'] < 60)    $tips[] = 'Create a shared cleaning rota on day one';
        if ($dims['noise_tolerance'] < 60) $tips[] = 'Use headphones when studying or listening to music';
        if ($dims['social_level'] < 60)   $tips[] = 'Discuss guest and party policies upfront';
        return $tips;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent match ⭐⭐⭐',
            $score >= 70 => 'Good match ⭐⭐',
            $score >= 55 => 'Workable ⭐',
            $score >= 40 => 'Challenging',
            default      => 'Not recommended',
        };
    }
}
