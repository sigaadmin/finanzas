<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\CalculateOwnRevenueProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\CalculateOwnRevenueProposalRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueProposalCalculationController extends Controller
{
    public function __invoke(
        CalculateOwnRevenueProposalRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        CalculateOwnRevenueProposal $calculateProposal,
    ): RedirectResponse {
        $calculated = $calculateProposal->handle(
            $budget,
            $proposal,
            $request->user(),
            $request->validated('proposal_fingerprint'),
        );

        Inertia::flash('success', 'La propuesta quedó calculada y protegida contra cambios.');

        return to_route('finance.own-revenue.budgets.planning.show', [
            'budget' => $budget,
            'proposal_version' => $calculated->version_number,
        ]);
    }
}
