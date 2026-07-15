<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteProposalTravelCommission;
use App\Actions\Finance\OwnRevenue\Planning\StoreProposalTravelCommission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreProposalTravelCommissionRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueProposalTravelCommissionController extends Controller
{
    public function store(StoreProposalTravelCommissionRequest $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, StoreProposalTravelCommission $action): RedirectResponse
    {
        $action->handle($proposal, $request->user(), $request->validated());
        Inertia::flash('success', 'Comisión guardada.');

        return back();
    }

    public function update(StoreProposalTravelCommissionRequest $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $travelCommission, StoreProposalTravelCommission $action): RedirectResponse
    {
        $action->handle($proposal, $request->user(), $request->validated(), $travelCommission);
        Inertia::flash('success', 'Comisión actualizada.');

        return back();
    }

    public function destroy(Request $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $travelCommission, DeleteProposalTravelCommission $action): RedirectResponse
    {
        $action->handle($proposal, $travelCommission, $request->user());
        Inertia::flash('success', 'Comisión eliminada.');

        return back();
    }
}
