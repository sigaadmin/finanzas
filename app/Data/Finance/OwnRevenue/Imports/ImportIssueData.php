<?php

namespace App\Data\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;

final readonly class ImportIssueData
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public OwnRevenueImportIssueSeverity $severity,
        public string $code,
        public ?string $field,
        public string $message,
        public array $context = [],
        public ?string $sheetName = null,
        public ?int $rowNumber = null,
    ) {}
}
