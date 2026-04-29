<?php

namespace App\Command\AI;

use App\Ml\Model\PriceRecommendationModel;
use App\Ml\Preprocessor\ListingFeatureExtractor;
use App\Repository\ListingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:train-price',
    description: 'Train the price recommendation model using active listing prices.',
)]
class TrainPriceCommand extends Command
{
    public function __construct(
        private readonly PriceRecommendationModel $model,
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
        $io->title('Training Price Recommendation Model');

        try {
            $listings = $this->listingRepository->findBy(['status' => 'active'], null, 1000);
        } catch (\Exception) {
            $listings = [];
            $io->warning('Could not connect to database. Falling back to synthetic data.');
        }

        $samples = [];
        $prices = [];

        foreach ($listings as $listing) {
            $price = (float) $listing->getPrice();
            if ($price <= 0) {
                continue;
            }
            $samples[] = $this->extractor->extract($listing);
            $prices[] = $price;
        }

        if (count($samples) < 30) {
            if ($input->getOption('synthetic') || count($samples) === 0) {
                $io->warning('Using synthetic training data.');
                $synthetic = PriceRecommendationModel::generateSyntheticSamples();
                $samples = array_merge($samples, $synthetic['samples']);
                $prices = array_merge($prices, $synthetic['prices']);
            } else {
                $io->error(sprintf('Only %d priced listings found. Need at least 30 or use --synthetic.', count($samples)));
                return Command::FAILURE;
            }
        }

        $io->info(sprintf('Training on %d samples…', count($samples)));

        $metrics = $this->model->train($samples, $prices);

        $io->success(sprintf(
            'Model trained! Cross-validation R²: %.3f  (%d samples)',
            $metrics['cross_val_r_squared'],
            $metrics['samples']
        ));

        return Command::SUCCESS;
    }
}
