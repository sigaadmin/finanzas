<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class AbpreAnalysis
{
    /**
     * @param  list<AbpreLineData>  $lines
     * @param  list<AbpreJustificationData>  $justifications
     * @param  list<ImportIssueData>  $issues
     * @param  list<array{sheet_name:string,row_number:int,row_kind:string,source_payload:array<string, ?string>,normalized_payload:array<string, mixed>}>  $sourceRows
     */
    public function __construct(
        public array $lines,
        public array $justifications,
        public array $issues,
        public array $sourceRows,
    ) {}
}
