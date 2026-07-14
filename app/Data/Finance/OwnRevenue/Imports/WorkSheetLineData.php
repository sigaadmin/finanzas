<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class WorkSheetLineData
{
    /**
     * @param  list<array{code:string,name:string}>  $sourceRegions
     * @param  array<int, string>  $months
     * @param  list<int>  $sourceRows
     */
    public function __construct(
        public string $activityCode,
        public string $activityName,
        public string $itemName,
        public string $specificItemCode,
        public string $regionCode,
        public string $regionName,
        public array $sourceRegions,
        public array $months,
        public string $annualAmountCents,
        public array $sourceRows,
    ) {}
}
