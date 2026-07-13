<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CopyExpenseClassificationsForYear
{
    private const DESTINATION_UNIQUE_INDEX = 'expense_classifications_fiscal_year_specific_item_code_unique';

    private const HIERARCHY_COLUMNS = [
        'chapter_code',
        'chapter_name',
        'concept_code',
        'concept_name',
        'generic_item_code',
        'generic_item_name',
        'specific_item_code',
        'specific_item_name',
        'expense_type_code',
        'expense_type_name',
    ];

    public function handle(OwnRevenueBudget $budget, ?int $sourceYear = null): OwnRevenueBudget
    {
        try {
            return DB::transaction(function () use ($budget, $sourceYear): OwnRevenueBudget {
                $budget = OwnRevenueBudget::query()
                    ->whereKey($budget->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $resolvedSourceYear = $this->resolveSourceYear($budget, $sourceYear);
                $sourceCatalog = $this->catalogForYear($resolvedSourceYear);

                if ($sourceCatalog->isEmpty()) {
                    throw ValidationException::withMessages([
                        'source_year' => 'El catálogo COG de origen no existe o no contiene partidas.',
                    ]);
                }

                $destinationCatalog = $this->catalogForYear($budget->fiscal_year);

                if ($destinationCatalog->isNotEmpty()) {
                    $this->ensureCatalogsMatch($sourceCatalog, $destinationCatalog);

                    if ($budget->cog_status === CogCatalogStatus::Confirmed
                        || $budget->cog_source_year === $resolvedSourceYear) {
                        return $budget;
                    }
                } else {
                    $this->insertCatalog($sourceCatalog, $budget->fiscal_year);
                }

                $budget->update([
                    'cog_source_year' => $resolvedSourceYear,
                    'cog_status' => CogCatalogStatus::PendingConfirmation,
                    'cog_confirmed_by' => null,
                    'cog_confirmed_at' => null,
                ]);

                return $budget->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isDestinationCatalogConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'catalog' => 'El catálogo COG del ejercicio destino cambió durante la copia; inténtelo nuevamente.',
            ]);
        }
    }

    private function resolveSourceYear(OwnRevenueBudget $budget, ?int $sourceYear): int
    {
        if ($sourceYear !== null) {
            if ($sourceYear >= $budget->fiscal_year) {
                throw ValidationException::withMessages([
                    'source_year' => 'El catálogo COG de origen debe pertenecer a un ejercicio anterior.',
                ]);
            }

            return $sourceYear;
        }

        $resolvedSourceYear = ExpenseClassification::query()
            ->where('fiscal_year', '<', $budget->fiscal_year)
            ->max('fiscal_year');

        if ($resolvedSourceYear === null) {
            throw ValidationException::withMessages([
                'source_year' => 'No existe un catálogo COG anterior para copiar.',
            ]);
        }

        return (int) $resolvedSourceYear;
    }

    /**
     * @return Collection<int, ExpenseClassification>
     */
    private function catalogForYear(int $fiscalYear): Collection
    {
        return ExpenseClassification::query()
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('specific_item_code')
            ->get(self::HIERARCHY_COLUMNS);
    }

    /**
     * @param  Collection<int, ExpenseClassification>  $sourceCatalog
     * @param  Collection<int, ExpenseClassification>  $destinationCatalog
     */
    private function ensureCatalogsMatch(Collection $sourceCatalog, Collection $destinationCatalog): void
    {
        $sourceRows = $sourceCatalog->map->only(self::HIERARCHY_COLUMNS)->values()->all();
        $destinationRows = $destinationCatalog->map->only(self::HIERARCHY_COLUMNS)->values()->all();

        if ($sourceRows !== $destinationRows) {
            throw ValidationException::withMessages([
                'catalog' => 'El catálogo COG del ejercicio destino entra en conflicto con el catálogo de origen.',
            ]);
        }
    }

    /**
     * @param  Collection<int, ExpenseClassification>  $sourceCatalog
     */
    private function insertCatalog(Collection $sourceCatalog, int $destinationYear): void
    {
        $timestamp = now();

        $sourceCatalog
            ->map(fn (ExpenseClassification $classification): array => [
                'fiscal_year' => $destinationYear,
                ...$classification->only(self::HIERARCHY_COLUMNS),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->chunk(500)
            ->each(fn (Collection $rows) => ExpenseClassification::query()->insert($rows->all()));
    }

    private function isDestinationCatalogConflict(UniqueConstraintViolationException $exception): bool
    {
        $isDestinationConstraint = $exception->columns === ['fiscal_year', 'specific_item_code']
            || $exception->index === self::DESTINATION_UNIQUE_INDEX;

        if (! $isDestinationConstraint) {
            return false;
        }

        $table = preg_quote((new ExpenseClassification)->getTable(), '/');
        $quotedTable = '(?:"'.$table.'"|`'.$table.'`|\['.$table.'\]|'.$table.')';
        $identifier = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_$]*)';

        return preg_match(
            '/^\s*insert\s+into\s+(?:'.$identifier.'\s*\.\s*)?'.$quotedTable.'(?=\s|\()/i',
            $exception->getSql(),
        ) === 1;
    }
}
