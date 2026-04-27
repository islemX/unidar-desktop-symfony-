<?php

namespace App\Message\AI;

final class BulkScoreListingsMessage
{
    public function __construct(
        /** @var int[] */
        public readonly array $listingIds = [],  // empty = all active listings
    ) {}
}
