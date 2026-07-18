<?php

namespace App\Actions\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfirmOwnRevenueFuelCommission
{
    public function handle(OwnRevenueFuelCommission $commission, User $user): OwnRevenueFuelCommission
    {
        Gate::forUser($user)->authorize('manageFuelOperations', $commission->fund->budget);

        return DB::transaction(function () use ($commission, $user): OwnRevenueFuelCommission {
            $fund = OwnRevenueFuelFund::query()->lockForUpdate()->findOrFail($commission->own_revenue_fuel_fund_id);
            Gate::forUser($user)->authorize('manageFuelOperations', $fund->budget);
            $lockedCommission = OwnRevenueFuelCommission::query()
                ->whereBelongsTo($fund, 'fund')->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            if ($lockedCommission->status !== OwnRevenueFuelCommissionStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'La comisión ya fue confirmada.']);
            }
            $consumed = (int) $fund->commissions()
                ->where('status', OwnRevenueFuelCommissionStatus::Confirmed)
                ->sum('amount_cents');
            $available = $fund->acquired_amount_cents - $consumed;
            if ($lockedCommission->amount_cents > $available) {
                throw ValidationException::withMessages(['amount_cents' => 'El fondo no tiene saldo suficiente; la comisión permanecerá pendiente.']);
            }
            $lockedCommission->update([
                'status' => OwnRevenueFuelCommissionStatus::Confirmed,
                'balance_after_cents' => $available - $lockedCommission->amount_cents,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            return $lockedCommission;
        }, attempts: 3);
    }
}
