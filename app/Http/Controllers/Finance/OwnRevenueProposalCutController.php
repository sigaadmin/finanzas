<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\StoreProposalCut;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreOwnRevenueProposalCutsRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use App\Services\Finance\OwnRevenue\Planning\ProportionalCutSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueProposalCutController extends Controller
{
    public function show(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        OwnRevenueCutReconciliation $reconciliation,
        ProportionalCutSuggestion $suggestion,
    ): Response {
        abort_unless($proposal->own_revenue_budget_id === $budget->id, 404);
        Gate::authorize('view', $budget);
        $data = $reconciliation->forProposal($proposal);

        return Inertia::render('finance/own-revenue/planning/cuts', [
            'budget' => ['id' => $budget->id, 'fiscal_year' => $budget->fiscal_year],
            'proposal' => ['id' => $proposal->id, 'version_number' => $proposal->version_number],
            'summary' => $data['summary'],
            'groups' => $data['groups'],
            'candidates' => $data['candidates'],
            'suggestion' => $suggestion->suggest($data['groups']),
            'blockers' => $data['blockers'],
            'reconciliation_fingerprint' => $data['fingerprint'],
            'permissions' => ['manage' => Gate::allows('manageProposalCuts', $budget)],
        ]);
    }

    public function store(
        StoreOwnRevenueProposalCutsRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        StoreProposalCut $storeCut,
    ): RedirectResponse {
        $storeCut->handle(
            $proposal,
            $request->user(),
            array_values($request->validated('cuts', [])),
            $request->validated('reconciliation_fingerprint'),
        );
        Inertia::flash('success', 'La distribución de disminuciones quedó guardada.');

        return to_route('finance.own-revenue.budgets.proposals.cuts.show', [$budget, $proposal]);
    }
}
