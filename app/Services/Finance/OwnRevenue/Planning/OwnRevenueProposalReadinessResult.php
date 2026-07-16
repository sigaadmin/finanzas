<?php

namespace App\Services\Finance\OwnRevenue\Planning;

final readonly class OwnRevenueProposalReadinessResult
{
    /** @param array<string, int> $fileIds @param list<string> $blockers */
    public function __construct(
        public bool $ready,
        public array $fileIds,
        public string $fingerprint,
        public array $blockers,
    ) {}
}
