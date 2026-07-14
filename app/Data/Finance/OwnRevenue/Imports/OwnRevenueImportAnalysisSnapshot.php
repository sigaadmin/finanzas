<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class OwnRevenueImportAnalysisSnapshot
{
    /**
     * @param  array<string, mixed>  $budget
     * @param  array<string, string>  $activityMap
     * @param  array<string, int>  $cogMap
     */
    public function __construct(
        public string $fingerprint,
        public array $budget,
        public array $activityMap,
        public array $cogMap,
    ) {}
}
