<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteOwnRevenueRoute
{
    public function handle(OwnRevenueBudget $budget, OwnRevenueRoute $route, User $user): void
    {
        Gate::forUser($user)->authorize('editProposal', $budget);

        DB::transaction(function () use ($budget, $route, $user): void {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedRoute = OwnRevenueRoute::query()->lockForUpdate()->findOrFail($route->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            if ($lockedRoute->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            if ($lockedRoute->fuelNeeds()->exists()) {
                throw ValidationException::withMessages([
                    'route' => 'No puedes eliminar un recorrido que ya se utiliza en la planeación.',
                ]);
            }

            $lockedRoute->delete();
        }, attempts: 3);
    }
}
