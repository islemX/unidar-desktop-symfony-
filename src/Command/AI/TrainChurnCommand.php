<?php

namespace App\Command\AI;

use App\Ml\Model\ChurnPredictionModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ai:train:churn', description: 'Train the churn prediction model')]
class TrainChurnCommand extends Command
{
    public function __construct(private readonly ChurnPredictionModel $model)
    { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Training Churn Prediction Model');

        [$samples, $labels] = $this->syntheticData();
        $result = $this->model->train($samples, $labels);

        $io->success(sprintf('Trained on %d samples', $result['samples']));
        return Command::SUCCESS;
    }

    private function syntheticData(): array
    {
        $samples = [];
        $labels  = [];

        // Retained users — active, engaged
        for ($i = 0; $i < 100; $i++) {
            $samples[] = [rand(0,7), 0.0, 0.0, (float)rand(5,30), (float)rand(20,100), (float)rand(5,20), (float)rand(1,5), 1.0, 1.0, (float)rand(60,500), 0.0];
            $labels[]  = 'retained';
        }

        // Churned users — inactive, incomplete profiles
        for ($i = 0; $i < 60; $i++) {
            $samples[] = [(float)rand(45,120), 1.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, (float)rand(10,60), 1.0];
            $labels[]  = 'churned';
        }

        return [$samples, $labels];
    }
}
