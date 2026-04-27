<?php

namespace App\Service\AI;

use App\Entity\User;
use App\Ml\Model\BehaviouralAnomalyModel;
use App\Ml\Storage\ModelRepository;
use App\Repository\ListingRepository;

/**
 * Feature 15: Behavioural Anomaly Detection
 * Detects suspicious account activity: scrapers, bulk messaging, fake accounts.
 */
class BehaviouralAnomalyService
{
    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly BehaviouralAnomalyModel $model,
        private readonly ListingRepository $listingRepository,
    ) {}

    /**
     * @return array [risk_score:0-100, risk_level, signals, action]
     */
    public function analyzeUser(User $user, array $activityLog = []): array
    {
        $signals = $this->extractSignals($user, $activityLog);
        $score   = $this->ruleScore($signals);

        if ($this->modelRepository->exists(BehaviouralAnomalyModel::MODEL_NAME)) {
            $mlScore = $this->model->predict($signals);
            $score   = (int) round($score * 0.35 + $mlScore * 0.65);
        }

        $score = min(100, max(0, $score));
        $level = match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'suspicious',
            $score >= 25 => 'watch',
            default      => 'normal',
        };

        return [
            'risk_score'  => $score,
            'risk_level'  => $level,
            'signals'     => $this->describeSignals($signals),
            'action'      => $this->recommendAction($level),
            'explanation' => $this->explain($signals),
        ];
    }

    public function extractSignals(User $user, array $log): array
    {
        $createdAt = $user->getCreatedAt() ?? new \DateTimeImmutable();
        $ageDays   = max(0, (new \DateTimeImmutable())->diff($createdAt)->days);

        // Message rate: messages sent per day since account creation
        $msgCount  = $log['message_count'] ?? 0;
        $msgRate   = $ageDays > 0 ? $msgCount / $ageDays : $msgCount;

        // Listing view rate (fast sequential views = scraper)
        $viewCount     = $log['view_count']     ?? 0;
        $sessionCount  = $log['session_count']  ?? 1;
        $viewsPerSess  = $sessionCount > 0 ? $viewCount / $sessionCount : $viewCount;

        // Login anomalies
        $loginLocations = count(array_unique($log['ip_addresses'] ?? []));

        return [
            'account_age_days'     => $ageDays,
            'new_account'          => (float) ($ageDays < 3),
            'msg_rate_per_day'     => round($msgRate, 2),
            'high_msg_rate'        => (float) ($msgRate > 20),
            'views_per_session'    => round($viewsPerSess, 1),
            'scraper_pattern'      => (float) ($viewsPerSess > 80),
            'unique_ip_count'      => $loginLocations,
            'ip_anomaly'           => (float) ($loginLocations > 5),
            'no_profile_photo'     => (float) (method_exists($user, 'getProfilePhoto') ? empty($user->getProfilePhoto()) : 0.0),
            'no_phone'             => (float) empty($user->getPhone()),
            'bulk_contact'         => (float) (($log['unique_contacts'] ?? 0) > 30),
            'failed_logins'        => (float) (($log['failed_logins'] ?? 0) > 10),
            'listing_count'        => $this->listingRepository->count(['owner' => $user]),
            'account_incomplete'   => (float) ($this->profileCompleteness($user) < 0.4),
        ];
    }

    private function ruleScore(array $s): int
    {
        $score = 0;
        if ($s['new_account'])        $score += 15;
        if ($s['high_msg_rate'])      $score += 30;
        if ($s['scraper_pattern'])    $score += 35;
        if ($s['ip_anomaly'])         $score += 20;
        if ($s['bulk_contact'])       $score += 25;
        if ($s['failed_logins'])      $score += 15;
        if ($s['account_incomplete']) $score += 10;
        return $score;
    }

    private function describeSignals(array $s): array
    {
        $out = [];
        if ($s['high_msg_rate'])    $out[] = 'Unusually high message rate (' . $s['msg_rate_per_day'] . '/day)';
        if ($s['scraper_pattern'])  $out[] = 'Bulk listing views per session (' . $s['views_per_session'] . ')';
        if ($s['ip_anomaly'])       $out[] = 'Login from ' . $s['unique_ip_count'] . ' different IP addresses';
        if ($s['bulk_contact'])     $out[] = 'Contacted many different listings/users';
        if ($s['failed_logins'])    $out[] = 'Multiple failed login attempts';
        if ($s['new_account'])      $out[] = 'Account created very recently';
        return $out;
    }

    private function explain(array $s): string
    {
        if ($s['scraper_pattern']) return 'Account shows data-scraping behaviour pattern.';
        if ($s['high_msg_rate'])   return 'Account is sending messages at an unusual rate — possible spam.';
        if ($s['ip_anomaly'])      return 'Account accessed from many different IPs — possible credential sharing.';
        return 'Behaviour within normal parameters.';
    }

    private function recommendAction(string $level): string
    {
        return match ($level) {
            'critical'   => 'Suspend account immediately and notify admin',
            'suspicious' => 'Require CAPTCHA + phone verification',
            'watch'      => 'Monitor next 48 hours, rate-limit messages',
            default      => 'No action required',
        };
    }

    private function profileCompleteness(User $user): float
    {
        $fields = [
            $user->getFullName(),
            $user->getEmail(),
            $user->getPhone(),
            $user->getUniversity(),
            $user->getGender(),
            method_exists($user, 'getProfilePhoto') ? $user->getProfilePhoto() : null,
        ];
        $filled = count(array_filter($fields, fn($v) => $v !== null && $v !== ''));
        return $filled / count($fields);
    }
}
