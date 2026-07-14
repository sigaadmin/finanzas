<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class AbpreJustificationData
{
    public function __construct(
        public string $chapterCode,
        public string $chapterName,
        public string $specificItemCode,
        public string $specificItemName,
        public string $budgetProgramCode,
        public string $componentName,
        public ?string $goalsImpact,
        public string $justification,
        public int $sourceRow,
    ) {}
}
