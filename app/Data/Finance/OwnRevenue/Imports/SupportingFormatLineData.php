<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class SupportingFormatLineData
{
    /** @param array<string, int|string|null> $values */
    public function __construct(
        public string $format,
        public string $sourceSheet,
        public int $sourceRow,
        public array $values,
    ) {}
}
