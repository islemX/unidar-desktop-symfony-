<?php

namespace App\Service\AI;

use App\Entity\User;
use App\Ml\Model\ChurnPredictionModel;
use App\Ml\Storage\ModelRepository;

/**
 * Feature 17: Churn Predictor
 * Identifies students likely to leave the platform and suggests re-engagement.
 */
class ChurnPredictionService
{
    public function __construct(
        private readonly ModelRepository $modelRepository,
        private readonly ChurnPredictionModel $model,
    ) {}

    public function predict(User $user, array $activityData = []): array
    {
        $features    = $this->extractFeatures($user, $activityData);
        $probability = $this->modelRepository->exists(ChurnPredictionModel::MODEL_NAME)
            ? $this->model->predictProbability($features)
            : $this->ruleBasedProbability($features);

        $probability = max(0.0, min(1.0, $probability));
        $risk        = match (true) {
            $probability >= 0.70 => 'high',
            $probability >= 0.40 => 'medium',
            default              => 'low',
        };

        return [
            'churn_probability' => round($probability, 2),
            'churn_percent'     => (int) round($probability * 100),
            'risk'              => $risk,
            'days_since_login'  => $features['days_since_login'],
            'reasons'           => $this->inferReasons($features),
            're_engagement'     => $this->reEngagementActions($risk, $features),
        ];
    }

    /**
     * Batch prediction for all users — returns top at-risk users.
     * @param array $users  [['user' => User, 'activity' => array], ...]
     * @return array Sorted by churn probability desc
     */
    public function batchPredict(array $users): array
    {
        $results = [];
        foreach ($users as $item) {
            $result    = $this->predict($item['user'], $item['activity'] ?? []);
            $results[] = array_merge($result, ['user_id' => $item['user']->getId()]);
        }
        usort($results, fn($a, $b) => $b['churn_probability'] <=> $a['churn_probability']);
        return $results;
    }

    private function extractFeatures(User $user, array $activity): array
    {
        $lastLogin = $user->getLastLoginAt() ?? $user->getCreatedAt() ?? new \DateTimeImmutable();
        $daysSince = (new \DateTimeImmutable())->diff($lastLogin)->days;

        return [
            'days_since_login'      => $daysSince,
            'inactive_30_days'      => (float) ($daysSince > 30),
            'inactive_60_days'      => (float) ($daysSince > 60),
            'session_count_30d'     => (float) ($activity['session_count_30d']  ?? 0),
            'listing_views_30d'     => (float) ($activity['listing_views_30d']  ?? 0),
            'messages_sent_30d'     => (float) ($activity['messages_sent_30d']  ?? 0),
            'applications_30d'      => (float) ($activity['applications_30d']   ?? 0),
            'has_active_booking'    => (float) ($activity['has_active_booking'] ?? false),
            'profile_complete'      => (float) ($this->isProfileComplete($user)),
            'account_age_days'      => (float) max(1, (new \DateTimeImmutable())->diff($user->getCreatedAt() ?? new \DateTimeImmutable())->days),
            'notification_opt_out'  => (float) ($user->getNotificationPreferences()['email'] ?? true ? 0 : 1),
        ];
    }

    private function ruleBasedProbability(array $f): float
    {
        $prob = 0.05;
        if ($f['inactive_30_days'])   $prob += 0.30;
        if ($f['inactive_60_days'])   $prob += 0.25;
        if ($f['session_count_30d'] == 0) $prob += 0.20;
        if (!$f['profile_complete'])  $prob += 0.10;
        if ($f['has_active_booking']) $prob -= 0.25; // Less likely to churn if booked
        if ($f['notification_opt_out']) $prob += 0.10;
        return max(0.0, min(1.0, $prob));
    }

    private function inferReasons(array $f): array
    {
        $reasons = [];
        if ($f['days_since_login'] > 60) $reasons[] = 'No login for over 60 days';
        if ($f['days_since_login'] > 30) $reasons[] = 'Inactive for more than a month';
        if ($f['listing_views_30d'] == 0) $reasons[] = 'No listing views in past 30 days';
        if ($f['messages_sent_30d'] == 0) $reasons[] = 'No messages sent recently';
        if (!$f['profile_complete'])      $reasons[] = 'Profile incomplete — may not find value';
        return $reasons;
    }

    private function reEngagementActions(string $risk, array $features): array
    {
        $actions = [];
        if ($risk === 'high') {
            $actions[] = ['type' => 'email', 'template' => 'churn_winback', 'delay_days' => 0, 'priority' => 'urgent'];
            $actions[] = ['type' => 'push', 'message' => 'New listings matching your preferences just appeared!', 'delay_days' => 1];
        } elseif ($risk === 'medium') {
            $actions[] = ['type' => 'email', 'template' => 'listing_digest', 'delay_days' => 0, 'priority' => 'normal'];
        }
        if (!$features['profile_complete']) {
            $actions[] = ['type' => 'email', 'template' => 'complete_profile', 'delay_days' => 2, 'priority' => 'low'];
        }
        return $actions;
    }

    private function isProfileComplete(User $user): bool
    {
        return !empty($user->getFirstName())
            && !empty($user->getLastName())
            && !empty($user->getPhone())
            && !empty($user->getFieldOfStudy());
    }
}
