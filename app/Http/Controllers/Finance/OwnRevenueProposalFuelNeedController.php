<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteProposalFuelNeed;
use App\Actions\Finance\OwnRevenue\Planning\FuelNeedData;
use App\Actions\Finance\OwnRevenue\Planning\StoreProposalFuelNeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreProposalFuelNeedRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueProposalFuelNeedController extends Controller
{
    public function store(
        StoreProposalFuelNeedRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        StoreProposalFuelNeed $storeNeed,
    ): RedirectResponse {
        $storeNeed->handle($proposal, $request->user(), FuelNeedData::fromArray($request->validated()));
        Inertia::flash('success', 'Recorrido de combustible guardado.');

        return back();
    }

    public function update(
        StoreProposalFuelNeedRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        OwnRevenueProposalFuelNeed $fuelNeed,
        StoreProposalFuelNeed $storeNeed,
    ): RedirectResponse {
        $storeNeed->handle($proposal, $request->user(), FuelNeedData::fromArray($request->validated()), $fuelNeed);
        Inertia::flash('success', 'Recorrido de combustible actualizado.');

        return back();
    }

    public function destroy(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        OwnRevenueProposalFuelNeed $fuelNeed,
        DeleteProposalFuelNeed $deleteNeed,
    ): RedirectResponse {
        $deleteNeed->handle($proposal, $fuelNeed, $request->user());
        Inertia::flash('success', 'Recorrido de combustible eliminado.');

        return back();
    }
}
