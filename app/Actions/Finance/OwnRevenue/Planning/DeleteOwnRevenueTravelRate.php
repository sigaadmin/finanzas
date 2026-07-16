<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteOwnRevenueTravelRate
{
    public function handle(OwnRevenueBudget $budget, OwnRevenueTravelRate $rate, User $user): void
    {
        Gate::forUser($user)->authorize('editProposal', $budget);
        DB::transaction(function () use ($budget, $rate, $user): void {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedRate = OwnRevenueTravelRate::query()->lockForUpdate()->findOrFail($rate->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            if ($lockedRate->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            if ($lockedRate->participants()->exists()) {
                throw ValidationException::withMessages(['rate' => 'No puedes eliminar una tarifa que ya se utiliza en la planeación.']);
            }
            $lockedRate->delete();
        }, attempts: 3);
    }
}
