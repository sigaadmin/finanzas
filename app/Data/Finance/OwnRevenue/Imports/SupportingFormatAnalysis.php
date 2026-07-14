<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class SupportingFormatAnalysis
{
    /**
     * @param  list<SupportingFormatLineData>  $lines
     * @param  list<ImportIssueData>  $issues
     * @param  list<array{sheet_name:string,row_number:int,row_kind:string,source_payload:array<string, array{coordinate:string,value:?string,formula:?string}>,normalized_payload:array<string, mixed>}>  $sourceRows
     */
    public function __construct(
        public array $lines,
        public array $issues,
        public array $sourceRows,
    ) {}
}
