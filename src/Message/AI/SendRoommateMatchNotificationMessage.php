<?php

namespace App\Message\AI;

final class SendRoommateMatchNotificationMessage
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $matchCount,
    ) {}
}
