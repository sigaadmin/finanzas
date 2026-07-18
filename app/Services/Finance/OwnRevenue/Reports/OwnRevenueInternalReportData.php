<?php

namespace App\Services\Finance\OwnRevenue\Reports;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueBudgetBalance;
use App\Services\Finance\OwnRevenue\Fuel\OwnRevenueFuelSummary;
use Illuminate\Database\Eloquent\Collection;

class OwnRevenueInternalReportData
{
    public function __construct(
        private readonly OwnRevenueBudgetBalance $balances,
        private readonly OwnRevenueFuelSummary $fuelSummary,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function forBudget(OwnRevenueBudget $budget, array $input): array
    {
        $allLines = $budget->modifiedBudgetLines()
            ->withSum('incomingModifications', 'amount_cents')
            ->withSum('outgoingModifications', 'amount_cents')
            ->orderBy('chapter_code')
            ->orderBy('specific_item_code')
            ->orderBy('month')
            ->get();
        $filters = $this->normalizeFilters($allLines, $input);
        $lines = $allLines->filter(fn (OwnRevenueModifiedBudgetLine $line): bool => ($filters['chapter_code'] === null || $line->chapter_code === $filters['chapter_code'])
            && ($filters['specific_item_code'] === null || $line->specific_item_code === $filters['specific_item_code'])
            && ($filters['month'] === null || $line->month === $filters['month'])
        )->values();
        $rows = $lines->map(fn (OwnRevenueModifiedBudgetLine $line): array => $this->line($line))->all();

        return [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
            ],
            'filters' => [
                'applied' => $filters,
                'options' => $this->filterOptions($allLines),
            ],
            'has_initial_budget' => $budget->initialBudgets()->exists(),
            'summary' => $this->sumRows($rows),
            'lines' => $rows,
            'planning_vs_execution' => $this->planningVsExecution($rows),
            'modifications' => $this->modifications($budget, $lines->modelKeys()),
            'expense_dossiers' => $this->expenseDossiers($budget, $lines->modelKeys()),
            'fuel' => $this->fuelSummary->forBudget($budget),
        ];
    }

    /** @return array<string, int|string> */
    private function line(OwnRevenueModifiedBudgetLine $line): array
    {
        $modified = $this->balances->modifiedCents($line);
        $reserved = $this->balances->reservedCents($line);
        $committed = $this->balances->committedCents($line);
        $paid = $this->balances->paidCents($line);

        return [
            'id' => $line->id,
            'chapter_code' => $line->chapter_code,
            'chapter_name' => $line->chapter_name,
            'specific_item_code' => $line->specific_item_code,
            'specific_item_name' => $line->specific_item_name,
            'month' => $line->month,
            'initial_amount_cents' => (string) $line->getRawOriginal('initial_amount_cents'),
            'modified_amount_cents' => (string) $modified,
            'reserved_amount_cents' => (string) $reserved,
            'committed_amount_cents' => (string) $committed,
            'paid_amount_cents' => (string) $paid,
            'available_amount_cents' => (string) ($modified - $reserved - $committed - $paid),
        ];
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     * @return array{initial_amount_cents: string, modified_amount_cents: string, reserved_amount_cents: string, committed_amount_cents: string, paid_amount_cents: string, available_amount_cents: string}
     */
    private function sumRows(array $rows): array
    {
        $sum = static fn (string $key): string => (string) array_sum(array_column($rows, $key));

        return [
            'initial_amount_cents' => $sum('initial_amount_cents'),
            'modified_amount_cents' => $sum('modified_amount_cents'),
            'reserved_amount_cents' => $sum('reserved_amount_cents'),
            'committed_amount_cents' => $sum('committed_amount_cents'),
            'paid_amount_cents' => $sum('paid_amount_cents'),
            'available_amount_cents' => $sum('available_amount_cents'),
        ];
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     * @return array{planned_amount_cents: string, paid_amount_cents: string, difference_amount_cents: string, execution_percentage: ?string}
     */
    private function planningVsExecution(array $rows): array
    {
        $planned = (string) array_sum(array_column($rows, 'initial_amount_cents'));
        $paid = (string) array_sum(array_column($rows, 'paid_amount_cents'));

        return [
            'planned_amount_cents' => $planned,
            'paid_amount_cents' => $paid,
            'difference_amount_cents' => (string) ((int) $planned - (int) $paid),
            'execution_percentage' => $planned === '0'
                ? null
                : bcdiv(bcmul($paid, '100', 2), $planned, 2),
        ];
    }

    /**
     * @param  Collection<int, OwnRevenueModifiedBudgetLine>  $lines
     * @param  array<string, mixed>  $input
     * @return array{chapter_code: ?string, specific_item_code: ?string, month: ?int}
     */
    private function normalizeFilters(Collection $lines, array $input): array
    {
        $chapter = trim((string) ($input['chapter_code'] ?? ''));
        $item = trim((string) ($input['specific_item_code'] ?? ''));
        $month = filter_var($input['month'] ?? null, FILTER_VALIDATE_INT);

        return [
            'chapter_code' => $chapter !== '' && $lines->contains('chapter_code', $chapter) ? $chapter : null,
            'specific_item_code' => $item !== '' && $lines->contains('specific_item_code', $item) ? $item : null,
            'month' => is_int($month) && $lines->contains('month', $month) ? $month : null,
        ];
    }

    /**
     * @param  Collection<int, OwnRevenueModifiedBudgetLine>  $lines
     * @return array<string, list<array<string, int|string>>>
     */
    private function filterOptions(Collection $lines): array
    {
        return [
            'chapters' => $lines->unique('chapter_code')->map(fn (OwnRevenueModifiedBudgetLine $line): array => [
                'code' => $line->chapter_code,
                'name' => $line->chapter_name,
            ])->values()->all(),
            'items' => $lines->unique('specific_item_code')->map(fn (OwnRevenueModifiedBudgetLine $line): array => [
                'code' => $line->specific_item_code,
                'name' => $line->specific_item_name,
                'chapter_code' => $line->chapter_code,
            ])->values()->all(),
            'months' => $lines->pluck('month')->unique()->sort()->values()
                ->map(fn (int $month): array => ['value' => $month])->all(),
        ];
    }

    /**
     * @param  list<int>  $lineIds
     * @return array<string, mixed>
     */
    private function modifications(OwnRevenueBudget $budget, array $lineIds): array
    {
        $query = $budget->budgetModifications()
            ->where(function ($query) use ($lineIds): void {
                $query->whereIn('source_line_id', $lineIds)
                    ->orWhereIn('destination_line_id', $lineIds);
            });
        $total = (clone $query)->count();
        $transfers = (int) (clone $query)
            ->where('type', OwnRevenueBudgetModificationType::Transfer)
            ->sum('amount_cents');
        $reschedulings = (int) (clone $query)
            ->where('type', OwnRevenueBudgetModificationType::Rescheduling)
            ->sum('amount_cents');
        $items = $query
            ->with([
                'sourceLine:id,specific_item_code,specific_item_name,month',
                'destinationLine:id,specific_item_code,specific_item_name,month',
                'recordedBy:id,name',
            ])
            ->latest('recorded_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (OwnRevenueBudgetModification $modification): array => [
                'id' => $modification->id,
                'type' => $modification->type->value,
                'amount_cents' => (string) $modification->getRawOriginal('amount_cents'),
                'reason' => $modification->reason,
                'source' => [
                    'specific_item_code' => $modification->sourceLine->specific_item_code,
                    'specific_item_name' => $modification->sourceLine->specific_item_name,
                    'month' => $modification->sourceLine->month,
                ],
                'destination' => [
                    'specific_item_code' => $modification->destinationLine->specific_item_code,
                    'specific_item_name' => $modification->destinationLine->specific_item_name,
                    'month' => $modification->destinationLine->month,
                ],
                'recorded_by_name' => $modification->recordedBy->name,
                'recorded_at' => $modification->recorded_at?->toISOString(),
            ])->all();

        return [
            'total' => $total,
            'transfer_amount_cents' => (string) $transfers,
            'rescheduling_amount_cents' => (string) $reschedulings,
            'items' => $items,
        ];
    }

    /**
     * @param  list<int>  $lineIds
     * @return array{total: int, by_status: array<string, int>, pending_requirements: int}
     */
    private function expenseDossiers(OwnRevenueBudget $budget, array $lineIds): array
    {
        $query = $budget->expenseDossiers()
            ->whereIn('own_revenue_modified_budget_line_id', $lineIds);
        $counts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $byStatus = collect(OwnRevenueExpenseDossierStatus::cases())
            ->mapWithKeys(fn (OwnRevenueExpenseDossierStatus $status): array => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])->all();
        $pendingRequirements = OwnRevenueExpenseDossierRequirement::query()
            ->where('status', OwnRevenueExpenseRequirementStatus::Pending)
            ->whereHas('dossier', fn ($query) => $query
                ->where('own_revenue_budget_id', $budget->id)
                ->whereIn('own_revenue_modified_budget_line_id', $lineIds))
            ->count();

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'pending_requirements' => $pendingRequirements,
        ];
    }
}
