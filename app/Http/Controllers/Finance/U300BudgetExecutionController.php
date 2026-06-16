<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\CancelU300BudgetMovement;
use App\Actions\Finance\U300\StoreU300BudgetMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CancelU300BudgetMovementRequest;
use App\Http\Requests\Finance\StoreU300BudgetMovementRequest;
use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300BudgetMovement;
use App\Models\Finance\U300\U300Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class U300BudgetExecutionController extends Controller
{
    public function index(U300Program $program): Response
    {
        $program->load(
            'budgetVersions.budgetLines.action',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.movements',
        );
        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $lines = $adjustedVersion?->budgetLines
            ->sortBy('sort_order')
            ->values() ?? collect();

        return Inertia::render('finance/u300/programs/execution', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'lines' => $lines->map(fn (U300BudgetLine $line): array => [
                    'id' => $line->id,
                    'action_number' => $line->action->number,
                    'action_name' => $line->action->name,
                    'cog_code' => $line->expenseClassification?->specific_item_code,
                    'cog_name' => $line->expenseClassification?->specific_item_name,
                    'amount_cents' => $line->amount_cents,
                    'executed_cents' => $this->executedCents($line),
                    'available_cents' => $line->amount_cents - $this->executedCents($line),
                ]),
                'movements' => $lines
                    ->flatMap(fn (U300BudgetLine $line) => $line->movements->map(
                        fn (U300BudgetMovement $movement): array => [
                            'id' => $movement->id,
                            'line_id' => $line->id,
                            'line_label' => trim($line->action->number.' '.$line->action->name),
                            'type' => $movement->type,
                            'movement_date' => $movement->movement_date->toDateString(),
                            'concept' => $movement->concept,
                            'document_reference' => $movement->document_reference,
                            'amount_cents' => $movement->amount_cents,
                            'is_cancelled' => $movement->cancelled_at !== null,
                            'cancelled_at' => $movement->cancelled_at?->toDateTimeString(),
                            'cancellation_reason' => $movement->cancellation_reason,
                        ]
                    ))
                    ->sortByDesc('movement_date')
                    ->values(),
            ],
        ]);
    }

    public function store(
        StoreU300BudgetMovementRequest $request,
        U300Program $program,
        StoreU300BudgetMovement $storeMovement,
    ): RedirectResponse {
        $storeMovement->handle($program, $request->user(), $request->movement());

        return to_route('finance.u300.programs.execution.index', $program);
    }

    public function cancel(
        CancelU300BudgetMovementRequest $request,
        U300Program $program,
        U300BudgetMovement $movement,
        CancelU300BudgetMovement $cancelMovement,
    ): RedirectResponse {
        $cancelMovement->handle($program, $movement, $request->user(), $request->cancellationReason());

        return to_route('finance.u300.programs.execution.index', $program);
    }

    private function executedCents(U300BudgetLine $line): int
    {
        return (int) $line->movements
            ->filter(fn (U300BudgetMovement $movement): bool => $movement->cancelled_at === null)
            ->sum(
                fn (U300BudgetMovement $movement): int => $movement->type === 'reimbursement'
                    ? -$movement->amount_cents
                    : $movement->amount_cents
            );
    }
}
