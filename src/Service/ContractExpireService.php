<?php

namespace App\Service;

class ContractExpireService
{
    public function __construct(private ContractManager $contractManager) {}

    public function expireContracts(): int
    {
        return $this->contractManager->checkAndExpireContracts();
    }
}
