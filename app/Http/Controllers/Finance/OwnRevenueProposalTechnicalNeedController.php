<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteProposalTechnicalNeed;
use App\Actions\Finance\OwnRevenue\Planning\StoreProposalTechnicalNeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreProposalTechnicalNeedRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueProposalTechnicalNeedController extends Controller
{
    public function store(
        StoreProposalTechnicalNeedRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        StoreProposalTechnicalNeed $storeNeed,
    ): RedirectResponse {
        $storeNeed->handle($proposal, $request->user(), $request->validated());
        Inertia::flash('success', 'Concepto de Ficha técnica guardado.');

        return back();
    }

    public function update(
        StoreProposalTechnicalNeedRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        OwnRevenueProposalTechnicalNeed $technicalNeed,
        StoreProposalTechnicalNeed $storeNeed,
    ): RedirectResponse {
        $storeNeed->handle($proposal, $request->user(), $request->validated(), $technicalNeed);
        Inertia::flash('success', 'Concepto de Ficha técnica actualizado.');

        return back();
    }

    public function destroy(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        OwnRevenueProposalTechnicalNeed $technicalNeed,
        DeleteProposalTechnicalNeed $deleteNeed,
    ): RedirectResponse {
        $deleteNeed->handle($proposal, $technicalNeed, $request->user());
        Inertia::flash('success', 'Concepto de Ficha técnica eliminado.');

        return back();
    }
}
