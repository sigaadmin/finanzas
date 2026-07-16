<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\CreateOwnRevenueProposalFromImports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\CreateOwnRevenueProposalRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueProposalCreationController extends Controller
{
    public function __invoke(
        CreateOwnRevenueProposalRequest $request,
        OwnRevenueBudget $budget,
        CreateOwnRevenueProposalFromImports $createProposal,
    ): RedirectResponse {
        $createProposal->handle(
            $budget,
            $request->user(),
            $request->sourceFileIds(),
            $request->validated('source_fingerprint'),
        );

        Inertia::flash('success', 'Propuesta creada desde las importaciones confirmadas.');

        return to_route('finance.own-revenue.budgets.show', $budget);
    }
}
