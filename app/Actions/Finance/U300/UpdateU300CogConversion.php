<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Action;
use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateU300CogConversion
{
    /**
     * @param  list<array{id: int|null, u300_action_id: int, amount_cents: int, expense_classification_code: string|null, exercise_month: string|null}>  $lines
     * @param  list<array{id: int, justification: string|null}>  $actions
     */
    public function handle(U300Program $program, array $lines, array $actions): U300Program
    {
        return DB::transaction(function () use ($program, $lines, $actions): U300Program {
            $adjustedVersion = $program->budgetVersions()
                ->where('kind', 'adjusted')
                ->with('budgetLines.movements')
                ->first();

            if (! $adjustedVersion) {
                return $program->refresh()->load('budgetVersions.budgetLines.expenseClassification');
            }

            if ($adjustedVersion->budgetLines->flatMap->movements->whereNull('cancelled_at')->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'No es posible modificar las partidas COG porque ya existen movimientos presupuestales activos.',
                ]);
            }

            $existingLines = $adjustedVersion->budgetLines
                ->filter(fn (U300BudgetLine $line): bool => $line->amount_cents > 0)
                ->keyBy('id');
            $allowedTotalsByAction = $existingLines
                ->groupBy('u300_action_id')
                ->map(fn ($actionLines): int => (int) $actionLines->sum('amount_cents'));
            $existingDescriptionsByAction = $existingLines
                ->groupBy('u300_action_id')
                ->map(fn ($actionLines): ?string => $actionLines->first()?->description);
            $submittedTotalsByAction = collect($lines)
                ->groupBy('u300_action_id')
                ->map(fn ($actionLines): int => (int) $actionLines->sum('amount_cents'));
            $allowedActionIds = $allowedTotalsByAction->keys();
            $submittedActionIds = collect($actions)->pluck('id');

            if ($submittedActionIds->diff($allowedActionIds)->isNotEmpty() || $allowedActionIds->diff($submittedActionIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'actions' => 'Todas las acciones con monto adecuado deben conservar su justificación.',
                ]);
            }

            foreach ($submittedTotalsByAction as $actionId => $submittedTotal) {
                $allowedTotal = $allowedTotalsByAction->get($actionId);

                if ($allowedTotal === null || $submittedTotal !== $allowedTotal) {
                    throw ValidationException::withMessages([
                        'lines' => 'La suma de partidas COG debe coincidir con el monto adecuado de cada acción.',
                    ]);
                }
            }

            foreach ($allowedTotalsByAction as $actionId => $allowedTotal) {
                if ($submittedTotalsByAction->get($actionId) !== $allowedTotal) {
                    throw ValidationException::withMessages([
                        'lines' => 'Todas las acciones con monto adecuado deben conservar su total en COG.',
                    ]);
                }
            }

            U300Action::query()
                ->whereIn('id', $submittedActionIds)
                ->get()
                ->each(function (U300Action $action) use ($actions): void {
                    $actionData = collect($actions)->firstWhere('id', $action->id);

                    $action->update([
                        'justification' => $actionData['justification'] ?? null,
                    ]);
                });

            $classifications = ExpenseClassification::query()
                ->where('fiscal_year', $program->fiscal_year)
                ->whereIn('specific_item_code', collect($lines)->pluck('expense_classification_code')->filter()->unique())
                ->get()
                ->keyBy('specific_item_code');
            $submittedIds = collect($lines)->pluck('id')->filter()->all();

            foreach ($existingLines as $line) {
                if (! in_array($line->id, $submittedIds, true)) {
                    $line->delete();
                }
            }

            foreach ($lines as $index => $lineData) {
                $classificationId = null;

                if ($lineData['expense_classification_code'] !== null) {
                    $classification = $classifications->get($lineData['expense_classification_code']);

                    if (! $classification) {
                        throw ValidationException::withMessages([
                            'lines' => "La partida {$lineData['expense_classification_code']} no existe en el COG {$program->fiscal_year}.",
                        ]);
                    }

                    $classificationId = $classification->id;
                }

                $budgetLine = $lineData['id'] !== null
                    ? $existingLines->get($lineData['id'])
                    : null;

                if ($lineData['id'] !== null && ! $budgetLine) {
                    continue;
                }

                $attributes = [
                    'u300_action_id' => $lineData['u300_action_id'],
                    'expense_classification_id' => $classificationId,
                    'amount_cents' => $lineData['amount_cents'],
                    'description' => $existingDescriptionsByAction->get($lineData['u300_action_id']),
                    'exercise_month' => $lineData['exercise_month'],
                    'justification' => null,
                    'sort_order' => $index + 1,
                ];

                if ($budgetLine) {
                    $budgetLine->update($attributes);

                    continue;
                }

                $adjustedVersion->budgetLines()->create($attributes);
            }

            return $program->refresh()->load('budgetVersions.budgetLines.expenseClassification');
        });
    }
}
