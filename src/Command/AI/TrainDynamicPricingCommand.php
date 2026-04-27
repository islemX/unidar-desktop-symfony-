<?php

namespace App\Command\AI;

use App\Ml\Model\DynamicPricingModel;
use App\Repository\ListingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:dynamic-pricing', description: 'Train the dynamic pricing model')]
class TrainDynamicPricingCommand extends Command
{
    public function __construct(
        private readonly DynamicPricingModel $model,
        private readonly ListingRepository   $listingRepo,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Dynamic Pricing Model');

        $samples = [];
        $prices  = [];

        // Synthetic data across seasons and property types
        $types    = ['studio', 'apartment', 'house', 'room'];
        $cities   = ['tunis', 'sousse', 'sfax'];
        $basePrices = ['studio' => 450, 'apartment' => 700, 'house' => 1000, 'room' => 300];

        for ($i = 0; $i < 300; $i++) {
            $type    = $types[array_rand($types)];
            $month   = rand(1, 12);
            $rooms   = rand(1, 4);
            $area    = rand(25, 150);
            $active  = rand(5, 40);
            $vacancy = rand(10, 50) / 100;
            $base    = $basePrices[$type] ?? 500;
            $seasonal = in_array($month, [8, 9, 10]) ? 1.12 : (in_array($month, [6, 7]) ? 0.92 : 1.0);
            $price   = (float) round($base * $rooms * 0.7 * $seasonal + rand(-50, 50), -1);

            $samples[] = DynamicPricingModel::buildFeatures($type, 'tunis', $rooms, (float)$area, $month, $active, $vacancy, (float)$base);
            $prices[]  = $price;
        }

        $result = $this->model->train($samples, $prices);
        $io->success(sprintf('Trained on %d samples', $result['samples']));

        return Command::SUCCESS;
    }
}
