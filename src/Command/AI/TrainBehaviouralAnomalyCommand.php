<?php

namespace App\Command\AI;

use App\Ml\Model\BehaviouralAnomalyModel;
use App\Repository\UserRepository;
use App\Service\AI\BehaviouralAnomalyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:behavioural-anomaly', description: 'Train the behavioural anomaly detection model')]
class TrainBehaviouralAnomalyCommand extends Command
{
    public function __construct(
        private readonly BehaviouralAnomalyModel   $model,
        private readonly BehaviouralAnomalyService $service,
        private readonly UserRepository            $userRepo,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Behavioural Anomaly Detection Model');

        [$samples, $labels] = $this->generateSyntheticData();
        $io->writeln(sprintf('Synthetic samples: %d', count($samples)));

        // Augment with real users (all labelled normal — no confirmed anomalies in DB)
        $users = $this->userRepo->findAll();
        $io->writeln(sprintf('Real users to augment: %d', count($users)));

        foreach (array_slice($users, 0, 200) as $user) {
            try {
                $signals    = $this->service->extractSignals($user, []);
                $samples[]  = $signals;
                $labels[]   = 'normal';
            } catch (\Throwable) {
                // skip problematic users
            }
        }

        $result = $this->model->train($samples, $labels);
        $io->success(sprintf('Trained on %d samples (%d normal, %d anomaly)',
            $result['samples'],
            count(array_filter($labels, fn($l) => $l === 'normal')),
            count(array_filter($labels, fn($l) => $l === 'anomaly'))
        ));

        return Command::SUCCESS;
    }

    /**
     * Generate realistic synthetic signal sets for training.
     * Normal users (300) + known anomalies (150).
     */
    private function generateSyntheticData(): array
    {
        $samples = [];
        $labels  = [];

        // ── Normal users ─────────────────────────────────────────
        for ($i = 0; $i < 300; $i++) {
            $samples[] = [
                'account_age_days'   => rand(30, 1500),
                'new_account'        => 0.0,
                'msg_rate_per_day'   => round(rand(0, 5) / 10, 2),
                'high_msg_rate'      => 0.0,
                'views_per_session'  => round(rand(2, 15), 1),
                'scraper_pattern'    => 0.0,
                'unique_ip_count'    => rand(1, 3),
                'ip_anomaly'         => 0.0,
                'no_profile_photo'   => (float) rand(0, 1),
                'no_phone'           => (float) (rand(0, 3) === 0),
                'bulk_contact'       => 0.0,
                'failed_logins'      => 0.0,
                'account_incomplete' => (float) (rand(0, 4) === 0),
            ];
            $labels[] = 'normal';
        }

        // ── Scrapers ─────────────────────────────────────────────
        for ($i = 0; $i < 50; $i++) {
            $samples[] = [
                'account_age_days'   => rand(1, 14),
                'new_account'        => 1.0,
                'msg_rate_per_day'   => round(rand(0, 30) / 10, 2),
                'high_msg_rate'      => 0.0,
                'views_per_session'  => rand(90, 300),
                'scraper_pattern'    => 1.0,
                'unique_ip_count'    => rand(1, 3),
                'ip_anomaly'         => 0.0,
                'no_profile_photo'   => 1.0,
                'no_phone'           => 1.0,
                'bulk_contact'       => (float) rand(0, 1),
                'failed_logins'      => 0.0,
                'account_incomplete' => 1.0,
            ];
            $labels[] = 'anomaly';
        }

        // ── Spammers / bulk message senders ──────────────────────
        for ($i = 0; $i < 50; $i++) {
            $samples[] = [
                'account_age_days'   => rand(1, 30),
                'new_account'        => (float) (rand(0, 10) < 4),
                'msg_rate_per_day'   => rand(25, 80),
                'high_msg_rate'      => 1.0,
                'views_per_session'  => round(rand(5, 25), 1),
                'scraper_pattern'    => 0.0,
                'unique_ip_count'    => rand(1, 4),
                'ip_anomaly'         => 0.0,
                'no_profile_photo'   => 1.0,
                'no_phone'           => 1.0,
                'bulk_contact'       => 1.0,
                'failed_logins'      => 0.0,
                'account_incomplete' => 1.0,
            ];
            $labels[] = 'anomaly';
        }

        // ── Credential stuffers / IP anomalies ───────────────────
        for ($i = 0; $i < 50; $i++) {
            $samples[] = [
                'account_age_days'   => rand(5, 180),
                'new_account'        => 0.0,
                'msg_rate_per_day'   => round(rand(0, 10) / 10, 2),
                'high_msg_rate'      => 0.0,
                'views_per_session'  => round(rand(3, 20), 1),
                'scraper_pattern'    => 0.0,
                'unique_ip_count'    => rand(8, 25),
                'ip_anomaly'         => 1.0,
                'no_profile_photo'   => (float) rand(0, 1),
                'no_phone'           => (float) rand(0, 1),
                'bulk_contact'       => 0.0,
                'failed_logins'      => 1.0,
                'account_incomplete' => (float) rand(0, 1),
            ];
            $labels[] = 'anomaly';
        }

        return [$samples, $labels];
    }
}
