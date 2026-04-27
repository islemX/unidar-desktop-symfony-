<?php

namespace App\Command\AI;

use App\Ml\Model\FraudDetectionModel;
use App\Repository\ListingRepository;
use App\Service\AI\FraudDetectionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:fraud', description: 'Train the fraud detection model')]
class TrainFraudCommand extends Command
{
    public function __construct(
        private readonly FraudDetectionModel   $model,
        private readonly FraudDetectionService $service,
        private readonly ListingRepository     $listingRepo,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Fraud Detection Model');

        // Start with synthetic data
        $synthetic = FraudDetectionModel::generateSyntheticSamples();
        $samples   = $synthetic['samples'];
        $labels    = $synthetic['labels'];

        // Augment with real listings if available
        $listings  = $this->listingRepo->findAll();
        $io->writeln(sprintf('Real listings: %d | Synthetic: %d', count($listings), count($samples)));

        foreach ($listings as $listing) {
            $signals   = $this->service->extractSignals($listing);
            $samples[] = $signals;
            // In real deployment, labels would come from admin-verified fraud data
            $labels[]  = 'legit';
        }

        $result = $this->model->train($samples, $labels);
        $io->success(sprintf('Trained on %d samples', $result['samples']));

        return Command::SUCCESS;
    }
}
