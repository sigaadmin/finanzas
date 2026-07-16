<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteOwnRevenueTravelDestination
{
    public function handle(OwnRevenueBudget $budget, OwnRevenueTravelDestination $destination, User $user): void
    {
        Gate::forUser($user)->authorize('editProposal', $budget);
        DB::transaction(function () use ($budget, $destination, $user): void {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedDestination = OwnRevenueTravelDestination::query()->lockForUpdate()->findOrFail($destination->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            if ($lockedDestination->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            if ($lockedDestination->travelCommissions()->exists()) {
                throw ValidationException::withMessages(['destination' => 'No puedes eliminar un destino que ya se utiliza en la planeación.']);
            }
            $lockedDestination->delete();
        }, attempts: 3);
    }
}
