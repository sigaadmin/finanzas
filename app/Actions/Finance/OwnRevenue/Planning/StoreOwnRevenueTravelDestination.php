<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreOwnRevenueTravelDestination
{
    /** @param array<string, mixed> $data */
    public function handle(OwnRevenueBudget $budget, User $user, array $data, ?OwnRevenueTravelDestination $destination = null): OwnRevenueTravelDestination
    {
        Gate::forUser($user)->authorize('editProposal', $budget);

        return DB::transaction(function () use ($budget, $user, $data, $destination): OwnRevenueTravelDestination {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            $lockedDestination = $destination === null ? null : OwnRevenueTravelDestination::query()->lockForUpdate()->findOrFail($destination->id);
            if ($lockedDestination !== null && $lockedDestination->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            $name = Str::squish($data['destination']);
            $attributes = [
                'destination' => $name,
                'normalized_destination' => Str::lower($name),
                'food_zone' => $data['food_zone'],
                'lodging_zone' => $data['lodging_zone'],
                'is_active' => $data['is_active'] ?? true,
            ];
            if ($lockedDestination === null) {
                return $lockedBudget->travelDestinations()->create($attributes);
            }
            $lockedDestination->update($attributes);

            return $lockedDestination->refresh();
        }, attempts: 3);
    }
}
