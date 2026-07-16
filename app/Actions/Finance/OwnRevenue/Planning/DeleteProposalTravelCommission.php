<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteProposalTravelCommission
{
    public function handle(OwnRevenueProposal $proposal, OwnRevenueProposalTravelCommission $commission, User $user): void
    {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);
        DB::transaction(function () use ($proposal, $commission, $user): void {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $lockedCommission = OwnRevenueProposalTravelCommission::query()->lockForUpdate()->findOrFail($commission->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            if ($lockedCommission->own_revenue_proposal_id !== $lockedProposal->id) {
                abort(404);
            }
            $lockedCommission->delete();
            $lockedProposal->update(['total_amount_cents' => $lockedProposal->technicalNeeds()->sum('budget_amount_cents')
                + $lockedProposal->fuelNeeds()->sum('budget_amount_cents') + $lockedProposal->travelCommissions()->sum('total_amount_cents')]);
        }, attempts: 3);
    }
}
