<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\CreateOwnRevenueProposalRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\CreateOwnRevenueProposalRevisionRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueProposalRevisionController extends Controller
{
    public function __invoke(
        CreateOwnRevenueProposalRevisionRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        CreateOwnRevenueProposalRevision $createRevision,
    ): RedirectResponse {
        $revision = $createRevision->handle($budget, $proposal, $request->user());

        Inertia::flash('success', 'Se creó una nueva versión editable de la propuesta.');

        return to_route('finance.own-revenue.budgets.planning.show', [
            'budget' => $budget,
            'proposal_version' => $revision->version_number,
        ]);
    }
}
