<?php

namespace App\Message\AI;

final class TrainModelMessage
{
    public function __construct(
        public readonly string $modelType,   // 'listing_quality' | 'price' | 'roommate'
        public readonly bool $useSynthetic = false,
    ) {}
}
