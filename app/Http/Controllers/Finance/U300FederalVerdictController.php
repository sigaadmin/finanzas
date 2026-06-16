<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\UpdateU300FederalVerdict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateU300FederalVerdictRequest;
use App\Models\Finance\U300\U300Program;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class U300FederalVerdictController extends Controller
{
    public function edit(U300Program $program): Response
    {
        $program->load('projects.goals.actions.requestedItems');

        return Inertia::render('finance/u300/programs/verdict', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'requested_total_cents' => $program->requested_total_cents,
                'approved_total_cents' => $program->approved_total_cents,
                'federal_authorized_total_cents' => $program->federal_authorized_total_cents,
                'projects' => $program->projects->map(fn ($project): array => [
                    'id' => $project->id,
                    'number' => $project->number,
                    'name' => $project->name,
                    'goals' => $project->goals->map(fn ($goal): array => [
                        'id' => $goal->id,
                        'number' => $goal->number,
                        'description' => $goal->description,
                        'requested_total_cents' => $goal->requested_total_cents,
                        'approved_total_cents' => $goal->approved_total_cents,
                        'actions' => $goal->actions->map(fn ($action): array => [
                            'id' => $action->id,
                            'number' => $action->number,
                            'name' => $action->name,
                            'requested_total_cents' => $action->requested_total_cents,
                            'approved_total_cents' => $action->approved_total_cents,
                            'items' => $action->requestedItems->map(fn ($item): array => [
                                'id' => $item->id,
                                'expense_concept' => $item->expense_concept,
                                'expense_item' => $item->expense_item,
                                'period' => $item->period,
                                'total_cents' => $item->total_cents,
                                'approved_amount_cents' => $item->approved_amount_cents,
                                'approved_percentage' => $item->approved_percentage,
                            ]),
                        ]),
                    ]),
                ]),
            ],
        ]);
    }

    public function update(
        UpdateU300FederalVerdictRequest $request,
        U300Program $program,
        UpdateU300FederalVerdict $updateVerdict,
    ): RedirectResponse {
        $updateVerdict->handle(
            $program,
            $request->verdictItems(),
            $request->federalAuthorizedTotalCents(),
        );

        return to_route('finance.u300.programs.show', $program);
    }
}
