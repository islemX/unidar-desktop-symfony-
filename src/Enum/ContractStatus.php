<?php

namespace App\Enum;

enum ContractStatus: string
{
    case Draft = 'draft';
    case PendingSignature = 'pending_signature';
    case SignedByStudent = 'signed_by_student';
    case SignedByBoth = 'signed_by_both';
    case Active = 'active';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSignature => 'Pending Signature',
            self::SignedByStudent => 'Signed by Student',
            self::SignedByBoth => 'Signed by Both',
            self::Active => 'Active',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
