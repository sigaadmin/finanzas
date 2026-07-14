<?php

namespace App\Data\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;

final readonly class WorkbookDetection
{
    /** @param list<string> $evidence */
    public function __construct(
        public ?OwnRevenueImportFormat $format,
        public int $confidence,
        public ?int $detectedYear,
        public array $evidence,
    ) {}
}
