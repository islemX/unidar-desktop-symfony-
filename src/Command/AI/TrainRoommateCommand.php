<?php

namespace App\Command\AI;

use App\Ml\Model\RoommateCompatibilityModel;
use App\Ml\Preprocessor\UserFeatureExtractor;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:train-roommate',
    description: 'Train the roommate compatibility model using student preference data.',
)]
class TrainRoommateCommand extends Command
{
    public function __construct(
        private readonly RoommateCompatibilityModel $model,
        private readonly UserRepository $userRepository,
        private readonly UserFeatureExtractor $extractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('synthetic', null, InputOption::VALUE_NONE, 'Use synthetic data when DB has fewer than 30 students with preferences');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Roommate Compatibility Model');

        try {
            $students = $this->userRepository->findBy(['role' => UserRole::Student], null, 500);
        } catch (\Exception) {
            $students = [];
            $io->warning('Could not connect to database. Falling back to synthetic data.');
        }

        $samples = [];
        $labels = [];

        // Build pairwise dataset: random pairs labelled by heuristic compatibility
        foreach ($students as $studentA) {
            $prefA = $studentA->getRoommatePreference();
            if (!$prefA) {
                continue;
            }

            foreach ($students as $studentB) {
                if ($studentA->getId() === $studentB->getId()) {
                    continue;
                }
                $prefB = $studentB->getRoommatePreference();
                if (!$prefB) {
                    continue;
                }

                // Heuristic: combine feature vectors as difference
                $featA = $this->extractor->extract($studentA);
                $featB = $this->extractor->extract($studentB);
                $diff = array_map(fn($a, $b) => abs($a - $b), $featA, $featB);

                // Compatible: cleanliness diff <=1, same sleep schedule
                $cleanDiff = $diff[0];  // cleanliness index 0
                $sleepDiff = $diff[1];  // sleep index 1
                $smokingDiff = $diff[3]; // smoking index 3

                $label = ($cleanDiff <= 1 && $sleepDiff <= 0 && $smokingDiff <= 0) ? 'compatible' : 'incompatible';

                $samples[] = $diff;
                $labels[] = $label;

                if (count($samples) >= 200) {
                    break 2;
                }
            }
        }

        if (count($samples) < 20) {
            if ($input->getOption('synthetic') || count($samples) === 0) {
                $io->warning('Using synthetic training data (fewer than 20 student pairs found).');
                $synthetic = RoommateCompatibilityModel::generateSyntheticSamples();
                $samples = array_merge($samples, $synthetic['samples']);
                $labels = array_merge($labels, $synthetic['labels']);
            } else {
                $io->error(sprintf('Only %d pairs found. Need at least 20 or use --synthetic.', count($samples)));
                return Command::FAILURE;
            }
        }

        $io->info(sprintf('Training on %d pairwise samples…', count($samples)));

        $metrics = $this->model->train($samples, $labels);

        $io->success(sprintf(
            'Model trained! Cross-validation accuracy: %.1f%%  (%d samples)',
            $metrics['cross_val_accuracy'] * 100,
            $metrics['samples']
        ));

        return Command::SUCCESS;
    }
}
