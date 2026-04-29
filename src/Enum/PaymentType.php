<?php

namespace App\Enum;

enum PaymentType: string
{
    case MonthlyRent = 'monthly_rent';
    case Deposit = 'deposit';
    case SecurityDeposit = 'security_deposit';

    public function getLabel(): string
    {
        return match ($this) {
            self::MonthlyRent => 'Monthly Rent',
            self::Deposit => 'Deposit',
            self::SecurityDeposit => 'Security Deposit',
        };
    }
}
