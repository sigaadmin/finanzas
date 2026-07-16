<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\CreateAdjustedOwnRevenueProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\CreateAdjustedOwnRevenueProposalRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueProposalAdjustmentController extends Controller
{
    public function __invoke(
        CreateAdjustedOwnRevenueProposalRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        CreateAdjustedOwnRevenueProposal $createAdjusted,
    ): RedirectResponse {
        $adjusted = $createAdjusted->handle(
            $budget,
            $proposal,
            $request->user(),
            $request->validated('reconciliation_fingerprint'),
        );
        Inertia::flash('success', 'La propuesta ajustada quedó conciliada con el ABPRE final.');

        return to_route('finance.own-revenue.budgets.planning.show', [
            'budget' => $budget,
            'proposal_version' => $adjusted->version_number,
        ]);
    }
}
