<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class AbpreLineData
{
    /** @param array<int, string> $months @param list<int> $sourceRows */
    public function __construct(
        public string $responsibleUnitCode,
        public string $responsibleUnitName,
        public string $budgetProgramCode,
        public string $budgetProgramName,
        public string $componentCode,
        public string $componentName,
        public string $officialActivityCode,
        public string $officialActivityName,
        public string $regionCode,
        public string $regionName,
        public ?string $specificExpenseConceptCode,
        public string $specificItemCode,
        public array $months,
        public string $annualAmountCents,
        public array $sourceRows,
    ) {}
}
