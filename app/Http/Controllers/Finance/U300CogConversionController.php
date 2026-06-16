<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\UpdateU300CogConversion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateU300CogConversionRequest;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class U300CogConversionController extends Controller
{
    public function edit(U300Program $program): Response
    {
        $program->load('budgetVersions.budgetLines.action.goal.project', 'budgetVersions.budgetLines.expenseClassification');
        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $lines = $adjustedVersion?->budgetLines
            ?->filter(fn ($line): bool => $line->amount_cents > 0)
            ->sortBy('sort_order')
            ->values() ?? collect();

        return Inertia::render('finance/u300/programs/cog', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'actions' => $lines
                    ->groupBy('u300_action_id')
                    ->map(function ($actionLines): array {
                        $firstLine = $actionLines->first();
                        $action = $firstLine->action;

                        return [
                            'id' => $action->id,
                            'number' => $action->number,
                            'name' => $action->name,
                            'justification' => $action->justification,
                            'goal_number' => $action->goal->number,
                            'adjusted_total_cents' => (int) $actionLines->sum('amount_cents'),
                            'lines' => $actionLines
                                ->values()
                                ->map(fn ($line): array => [
                                    'id' => $line->id,
                                    'amount_cents' => $line->amount_cents,
                                    'description' => $line->description,
                                    'expense_classification_code' => $line->expenseClassification?->specific_item_code,
                                    'expense_classification_name' => $line->expenseClassification?->specific_item_name,
                                    'exercise_month' => $line->exercise_month,
                                ]),
                        ];
                    })
                    ->values()
                    ->all(),
            ],
            'classifications' => ExpenseClassification::query()
                ->where('fiscal_year', $program->fiscal_year)
                ->orderBy('specific_item_code')
                ->get(['id', 'specific_item_code', 'specific_item_name'])
                ->map(fn (ExpenseClassification $classification): array => [
                    'id' => $classification->id,
                    'specific_item_code' => $classification->specific_item_code,
                    'specific_item_name' => $classification->specific_item_name,
                ]),
        ]);
    }

    public function update(
        UpdateU300CogConversionRequest $request,
        U300Program $program,
        UpdateU300CogConversion $updateConversion,
    ): RedirectResponse {
        $updateConversion->handle($program, $request->lines(), $request->actions());

        return to_route('finance.u300.programs.show', $program);
    }
}
