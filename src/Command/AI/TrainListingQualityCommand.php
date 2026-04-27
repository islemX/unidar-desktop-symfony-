<?php

namespace App\Command\AI;

use App\Ml\Model\ListingQualityModel;
use App\Ml\Preprocessor\ListingFeatureExtractor;
use App\Repository\ListingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:train-listing-quality',
    description: 'Train the listing quality scoring model using existing listings data.',
)]
class TrainListingQualityCommand extends Command
{
    public function __construct(
        private readonly ListingQualityModel $model,
        private readonly ListingRepository $listingRepository,
        private readonly ListingFeatureExtractor $extractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('synthetic', null, InputOption::VALUE_NONE, 'Use synthetic data when DB has fewer than 30 listings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Listing Quality Model');

        try {
            $listings = $this->listingRepository->findBy(['status' => 'active'], null, 500);
        } catch (\Exception) {
            $listings = [];
            $io->warning('Could not connect to database. Falling back to synthetic data.');
        }

        $samples = [];
        $labels = [];

        foreach ($listings as $listing) {
            $features = $this->extractor->extract($listing);
            $samples[] = $features;

            // Heuristic label based on listing characteristics
            $photoCount = $listing->getImages()->count();
            $wordCount = str_word_count(strip_tags($listing->getDescription() ?? ''));
            $hasDates = $listing->getAvailableFrom() !== null;

            if ($photoCount >= 8 && $wordCount >= 100 && $hasDates) {
                $labels[] = 'excellent';
            } elseif ($photoCount >= 4 && $wordCount >= 50) {
                $labels[] = 'good';
            } else {
                $labels[] = 'fair';
            }
        }

        if (count($samples) < 30) {
            if ($input->getOption('synthetic') || count($samples) === 0) {
                $io->warning('Using synthetic training data (fewer than 30 real listings found).');
                $synthetic = ListingQualityModel::generateSyntheticSamples();
                $samples = array_merge($samples, $synthetic['samples']);
                $labels = array_merge($labels, $synthetic['labels']);
            } else {
                $io->error(sprintf('Only %d listings found. Need at least 30 or use --synthetic.', count($samples)));
                return Command::FAILURE;
            }
        }

        $io->info(sprintf('Training on %d samples…', count($samples)));

        $metrics = $this->model->train($samples, $labels);

        $io->success(sprintf(
            'Model trained! Cross-validation accuracy: %.1f%%  (%d samples)',
            $metrics['cross_val_accuracy'] * 100,
            $metrics['samples']
        ));

        return Command::SUCCESS;
    }
}
