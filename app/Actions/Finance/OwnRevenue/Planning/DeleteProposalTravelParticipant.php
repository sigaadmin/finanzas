<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProposalTravelParticipant
{
    public function handle(OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $commission, OwnRevenueProposalTravelParticipant $participant, User $user): void
    {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);
        DB::transaction(function () use ($proposal, $commission, $participant, $user): void {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $lockedCommission = OwnRevenueProposalTravelCommission::query()->lockForUpdate()->findOrFail($commission->id);
            $lockedParticipant = OwnRevenueProposalTravelParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            if ($lockedCommission->own_revenue_proposal_id !== $lockedProposal->id
                || $lockedParticipant->own_revenue_proposal_travel_commission_id !== $lockedCommission->id) {
                abort(404);
            }
            $lockedParticipant->delete();
            $participants = $lockedCommission->participants()->sum('total_amount_cents');
            $lockedCommission->update(['participants_amount_cents' => $participants, 'total_amount_cents' => $participants + $lockedCommission->flight_amount_cents]);
            $lockedProposal->update(['total_amount_cents' => $lockedProposal->technicalNeeds()->sum('budget_amount_cents')
                + $lockedProposal->fuelNeeds()->sum('budget_amount_cents') + $lockedProposal->travelCommissions()->sum('total_amount_cents')]);
        }, attempts: 3);
    }
}
