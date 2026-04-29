<?php

namespace App\Command\AI;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:retrain-all',
    description: 'Retrain all AI models (listing quality, price, roommate compatibility).',
)]
class RetrainAllModelsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('synthetic', null, InputOption::VALUE_NONE, 'Fall back to synthetic data when DB has insufficient records');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Retraining All AI Models');

        $syntheticFlag = $input->getOption('synthetic') ? ['--synthetic' => true] : [];
        $commands = [
            'app:ai:train-listing-quality',
            'app:ai:train-price',
            'app:ai:train-roommate',
            'ai:train:fraud',
            'ai:train:churn',
            'ai:train:dynamic-pricing',
        ];

        $failed = [];
        foreach ($commands as $commandName) {
            $io->section("Running: $commandName");
            $cmd = $this->getApplication()->find($commandName);
            $result = $cmd->run(new ArrayInput($syntheticFlag), $output);
            if ($result !== Command::SUCCESS) {
                $failed[] = $commandName;
            }
        }

        if (!empty($failed)) {
            $io->error('Some models failed to train: ' . implode(', ', $failed));
            return Command::FAILURE;
        }

        $io->success('All models retrained successfully!');
        return Command::SUCCESS;
    }
}
