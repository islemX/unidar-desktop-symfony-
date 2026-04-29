<?php

namespace App\Service;

class CommissionCalculator
{
    private float $commissionRate;

    public function __construct(float $commissionRate = 0.05)
    {
        $this->commissionRate = $commissionRate;
    }

    /**
     * Calculate the owner amount and platform fee from a given amount.
     *
     * @return array{ownerAmount: string, platformFee: string}
     */
    public function calculate(string $amount): array
    {
        if (function_exists('bcmul')) {
            $platformFee = bcmul($amount, (string) $this->commissionRate, 2);
            $ownerAmount = bcsub($amount, $platformFee, 2);
        } else {
            $platformFee = number_format((float) $amount * $this->commissionRate, 2, '.', '');
            $ownerAmount = number_format((float) $amount - (float) $platformFee, 2, '.', '');
        }

        return [
            'ownerAmount' => $ownerAmount,
            'platformFee' => $platformFee,
        ];
    }

    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }
}
