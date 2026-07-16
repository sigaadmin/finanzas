<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreOwnRevenueRoute
{
    public function __construct(private readonly FixedDecimal $decimal) {}

    /** @param array<string, mixed> $data */
    public function handle(
        OwnRevenueBudget $budget,
        User $user,
        array $data,
        ?OwnRevenueRoute $route = null,
    ): OwnRevenueRoute {
        Gate::forUser($user)->authorize('editProposal', $budget);

        return DB::transaction(function () use ($budget, $user, $data, $route): OwnRevenueRoute {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            $lockedRoute = $route === null
                ? null
                : OwnRevenueRoute::query()->lockForUpdate()->findOrFail($route->id);
            if ($lockedRoute !== null && $lockedRoute->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }

            $origin = Str::squish($data['origin']);
            $destination = Str::squish($data['destination']);
            $attributes = [
                'origin' => $origin,
                'normalized_origin' => Str::lower($origin),
                'destination' => $destination,
                'normalized_destination' => Str::lower($destination),
                'one_way_kilometers' => $this->decimal->requireNonNegative($data['one_way_kilometers']),
                'additional_kilometers' => $this->decimal->requireNonNegative($data['additional_kilometers'] ?? '0'),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ];

            if ($lockedRoute === null) {
                return $lockedBudget->planningRoutes()->create($attributes);
            }

            $lockedRoute->update($attributes);

            return $lockedRoute->refresh();
        }, attempts: 3);
    }
}
