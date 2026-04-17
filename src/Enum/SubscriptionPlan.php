<?php

namespace App\Enum;

enum SubscriptionPlan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getPrice(): float
    {
        return match ($this) {
            self::Monthly => 25.00,
            self::Yearly => 250.00,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
