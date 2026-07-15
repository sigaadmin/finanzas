<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreOwnRevenueTravelRate
{
    public function __construct(private readonly FixedDecimal $decimal) {}

    /** @param array<string, mixed> $data */
    public function handle(OwnRevenueBudget $budget, User $user, array $data, ?OwnRevenueTravelRate $rate = null): OwnRevenueTravelRate
    {
        Gate::forUser($user)->authorize('editProposal', $budget);

        return DB::transaction(function () use ($budget, $user, $data, $rate): OwnRevenueTravelRate {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('editProposal', $lockedBudget);
            $lockedRate = $rate === null ? null : OwnRevenueTravelRate::query()->lockForUpdate()->findOrFail($rate->id);
            if ($lockedRate !== null && $lockedRate->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            $position = Str::squish($data['position']);
            $attributes = [
                'position' => $position,
                'normalized_position' => Str::lower($position),
                'food_zone' => $data['food_zone'],
                'lodging_zone' => $data['lodging_zone'],
                'per_diem_uma' => $this->decimal->requireNonNegative($data['per_diem_uma']),
                'lodging_uma' => $this->decimal->requireNonNegative($data['lodging_uma']),
                'is_fallback' => $data['is_fallback'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ];
            if ($lockedRate === null) {
                return $lockedBudget->travelRates()->create($attributes);
            }
            $lockedRate->update($attributes);

            return $lockedRate->refresh();
        }, attempts: 3);
    }
}
