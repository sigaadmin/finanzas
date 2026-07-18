<?php

namespace App\Services\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;

class OwnRevenueFuelSummary
{
    /** @return array{acquired_amount_cents: string, confirmed_consumption_cents: string, pending_needs_cents: string, available_amount_cents: string} */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $fund = $budget->fuelFund()->first();
        $acquired = (int) ($fund?->getRawOriginal('acquired_amount_cents') ?? 0);
        $confirmed = $fund === null ? 0 : (int) $fund->commissions()
            ->where('status', OwnRevenueFuelCommissionStatus::Confirmed)
            ->sum('amount_cents');
        $pending = $fund === null ? 0 : (int) $fund->commissions()
            ->where('status', OwnRevenueFuelCommissionStatus::Pending)
            ->sum('amount_cents');

        return [
            'acquired_amount_cents' => (string) $acquired,
            'confirmed_consumption_cents' => (string) $confirmed,
            'pending_needs_cents' => (string) $pending,
            'available_amount_cents' => (string) ($acquired - $confirmed),
        ];
    }
}
