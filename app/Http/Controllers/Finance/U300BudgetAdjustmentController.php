<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\UpdateU300BudgetAdjustment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateU300BudgetAdjustmentRequest;
use App\Models\Finance\U300\U300Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class U300BudgetAdjustmentController extends Controller
{
    public function edit(U300Program $program): Response
    {
        $program->load('budgetVersions.budgetLines', 'projects.goals.actions.budgetLines');
        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $adjustedTotalsByAction = $adjustedVersion?->budgetLines
            ->groupBy('u300_action_id')
            ->map(fn ($lines): int => (int) $lines->sum('amount_cents')) ?? collect();

        return Inertia::render('finance/u300/programs/adjustment', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'approved_total_cents' => $program->approved_total_cents,
                'federal_authorized_total_cents' => $program->federal_authorized_total_cents,
                'adjustment_limit_cents' => $program->federal_authorized_total_cents ?? $program->approved_total_cents,
                'adjusted_total_cents' => $adjustedVersion?->total_cents ?? 0,
                'projects' => $program->projects->map(fn ($project): array => [
                    'id' => $project->id,
                    'number' => $project->number,
                    'name' => $project->name,
                    'goals' => $project->goals->map(fn ($goal): array => [
                        'id' => $goal->id,
                        'number' => $goal->number,
                        'description' => $goal->description,
                        'approved_total_cents' => $goal->approved_total_cents,
                        'actions' => $goal->actions
                            ->filter(fn ($action): bool => ($action->approved_total_cents ?? 0) > 0)
                            ->map(fn ($action): array => [
                                'id' => $action->id,
                                'number' => $action->number,
                                'name' => $action->name,
                                'approved_total_cents' => $action->approved_total_cents,
                                'adjusted_amount_cents' => $adjustedTotalsByAction->get($action->id, 0),
                                'adjusted_description' => $adjustedVersion
                                    ? $adjustedVersion->budgetLines
                                        ->firstWhere('u300_action_id', $action->id)?->description
                                    : null,
                            ])->values(),
                    ]),
                ]),
            ],
        ]);
    }

    public function update(
        UpdateU300BudgetAdjustmentRequest $request,
        U300Program $program,
        UpdateU300BudgetAdjustment $updateAdjustment,
    ): RedirectResponse {
        $updateAdjustment->handle($program, $request->user(), $request->allocations());

        return to_route('finance.u300.programs.show', $program);
    }
}
