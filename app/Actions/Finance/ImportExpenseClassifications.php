<?php

namespace App\Actions\Finance;

use App\Models\Finance\ExpenseClassification;
use App\Services\Finance\CogCatalogSpreadsheetParser;
use Illuminate\Support\Facades\DB;

class ImportExpenseClassifications
{
    public function __construct(private readonly CogCatalogSpreadsheetParser $parser) {}

    public function handle(int $fiscalYear, string $path): int
    {
        $rows = $this->parser->parse($path);

        return DB::transaction(function () use ($fiscalYear, $rows): int {
            foreach ($rows as $row) {
                ExpenseClassification::updateOrCreate(
                    [
                        'fiscal_year' => $fiscalYear,
                        'specific_item_code' => $row['specific_item_code'],
                    ],
                    [
                        'chapter_code' => $row['chapter_code'],
                        'chapter_name' => $row['chapter_name'],
                        'concept_code' => $row['concept_code'],
                        'concept_name' => $row['concept_name'],
                        'generic_item_code' => $row['generic_item_code'],
                        'generic_item_name' => $row['generic_item_name'],
                        'specific_item_name' => $row['specific_item_name'],
                        'expense_type_code' => $row['expense_type_code'],
                        'expense_type_name' => $row['expense_type_name'],
                    ],
                );
            }

            return count($rows);
        });
    }
}
