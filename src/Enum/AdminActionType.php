<?php

namespace App\Enum;

enum AdminActionType: string
{
    case VerifyUser = 'verify_user';
    case RejectVerification = 'reject_verification';
    case BanUser = 'ban_user';
    case SuspendUser = 'suspend_user';
    case ActivateUser = 'activate_user';
    case ResolveReport = 'resolve_report';
    case RemoveListing = 'remove_listing';

    public function getLabel(): string
    {
        return match ($this) {
            self::VerifyUser => 'Verify User',
            self::RejectVerification => 'Reject Verification',
            self::BanUser => 'Ban User',
            self::SuspendUser => 'Suspend User',
            self::ActivateUser => 'Activate User',
            self::ResolveReport => 'Resolve Report',
            self::RemoveListing => 'Remove Listing',
        };
    }
}
