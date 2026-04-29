<?php

namespace App\Command;

use App\Service\ContractExpireService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:expire-contracts',
    description: 'Mark contracts as completed when their end date has passed',
)]
class ExpireContractsCommand extends Command
{
    public function __construct(
        private ContractExpireService $contractExpireService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Contract Expiration Check');

        $count = $this->contractExpireService->expireContracts();

        if ($count === 0) {
            $io->success('No contracts to expire.');
        } else {
            $io->success(sprintf('%d contract(s) marked as completed.', $count));
        }

        return Command::SUCCESS;
    }
}
