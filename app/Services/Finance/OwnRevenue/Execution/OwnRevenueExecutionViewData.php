<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Support\Facades\Gate;

class OwnRevenueExecutionViewData
{
    public function __construct(private readonly OwnRevenueBudgetBalance $balances) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $lines = $budget->modifiedBudgetLines()
            ->withSum('incomingModifications', 'amount_cents')
            ->withSum('outgoingModifications', 'amount_cents')
            ->orderBy('chapter_code')
            ->orderBy('specific_item_code')
            ->orderBy('month')
            ->get();
        $lineData = $lines->map(fn (OwnRevenueModifiedBudgetLine $line): array => $this->line($line))->all();

        return [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
            ],
            'summary' => [
                'initial_amount_cents' => (string) array_sum(array_column($lineData, 'initial_amount_cents')),
                'modified_amount_cents' => (string) array_sum(array_column($lineData, 'modified_amount_cents')),
                'reserved_amount_cents' => (string) array_sum(array_column($lineData, 'reserved_amount_cents')),
                'committed_amount_cents' => (string) array_sum(array_column($lineData, 'committed_amount_cents')),
                'paid_amount_cents' => (string) array_sum(array_column($lineData, 'paid_amount_cents')),
                'available_amount_cents' => (string) array_sum(array_column($lineData, 'available_amount_cents')),
            ],
            'lines' => $lineData,
            'classifications' => $this->classifications($budget),
            'modifications' => $this->modifications($budget),
            'permissions' => ['manage' => Gate::allows('manageExecution', $budget)],
        ];
    }

    /** @return array<string, int|string> */
    private function line(OwnRevenueModifiedBudgetLine $line): array
    {
        $incoming = (int) ($line->incoming_modifications_sum_amount_cents ?? 0);
        $outgoing = (int) ($line->outgoing_modifications_sum_amount_cents ?? 0);
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
            'incoming_amount_cents' => (string) $incoming,
            'outgoing_amount_cents' => (string) $outgoing,
            'modified_amount_cents' => (string) $modified,
            'reserved_amount_cents' => (string) $reserved,
            'committed_amount_cents' => (string) $committed,
            'paid_amount_cents' => (string) $paid,
            'available_amount_cents' => (string) $this->balances->availableCents($line),
        ];
    }

    /** @return list<array<string, int|string>> */
    private function classifications(OwnRevenueBudget $budget): array
    {
        return ExpenseClassification::query()
            ->where('fiscal_year', $budget->fiscal_year)
            ->orderBy('specific_item_code')
            ->get(['id', 'chapter_code', 'chapter_name', 'specific_item_code', 'specific_item_name'])
            ->map(fn (ExpenseClassification $classification): array => [
                'id' => $classification->id,
                'chapter_code' => $classification->chapter_code,
                'chapter_name' => $classification->chapter_name,
                'specific_item_code' => $classification->specific_item_code,
                'specific_item_name' => $classification->specific_item_name,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function modifications(OwnRevenueBudget $budget): array
    {
        return $budget->budgetModifications()
            ->with(['sourceLine:id,specific_item_code,specific_item_name,month', 'destinationLine:id,specific_item_code,specific_item_name,month', 'recordedBy:id,name'])
            ->latest('recorded_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn ($modification): array => [
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
    }
}
