<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteProposalTravelParticipant;
use App\Actions\Finance\OwnRevenue\Planning\StoreProposalTravelParticipant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreProposalTravelParticipantRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueProposalTravelParticipantController extends Controller
{
    public function store(StoreProposalTravelParticipantRequest $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $travelCommission, StoreProposalTravelParticipant $action): RedirectResponse
    {
        $action->handle($proposal, $travelCommission, $request->user(), $request->validated());
        Inertia::flash('success', 'Participante guardado.');

        return back();
    }

    public function update(StoreProposalTravelParticipantRequest $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $travelCommission, OwnRevenueProposalTravelParticipant $participant, StoreProposalTravelParticipant $action): RedirectResponse
    {
        $action->handle($proposal, $travelCommission, $request->user(), $request->validated(), $participant);
        Inertia::flash('success', 'Participante actualizado.');

        return back();
    }

    public function destroy(Request $request, OwnRevenueBudget $budget, OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $travelCommission, OwnRevenueProposalTravelParticipant $participant, DeleteProposalTravelParticipant $action): RedirectResponse
    {
        $action->handle($proposal, $travelCommission, $participant, $request->user());
        Inertia::flash('success', 'Participante eliminado.');

        return back();
    }
}
