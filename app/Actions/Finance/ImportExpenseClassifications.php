<?php

namespace App\Actions\Finance;

use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\CogCatalogSpreadsheetParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ImportExpenseClassifications
{
    public function __construct(private readonly CogCatalogSpreadsheetParser $parser) {}

    public function handle(User $user, int $fiscalYear, string $path): int
    {
        Gate::forUser($user)->authorize('manage-expense-classifications');

        $rows = $this->parser->parse($path);

        return DB::transaction(function () use ($fiscalYear, $rows): int {
            $budget = OwnRevenueBudget::query()
                ->where('fiscal_year', $fiscalYear)
                ->lockForUpdate()
                ->first();

            if ($budget?->cog_status === CogCatalogStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'catalog' => 'No se puede importar sobre un catálogo COG confirmado.',
                ]);
            }

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
