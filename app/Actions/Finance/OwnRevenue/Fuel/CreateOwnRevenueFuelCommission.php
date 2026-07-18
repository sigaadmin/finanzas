<?php

namespace App\Actions\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateOwnRevenueFuelCommission
{
    /** @param array<string, mixed> $data */
    public function handle(OwnRevenueFuelFund $fund, User $user, array $data): OwnRevenueFuelCommission
    {
        Gate::forUser($user)->authorize('manageFuelOperations', $fund->budget);
        $litersScaled = $this->positiveDecimalScaled((string) $data['liters'], 'liters');
        $this->positiveDecimalScaled((string) $data['kilometers'], 'kilometers');
        $amountCents = (int) $data['amount_cents'];
        if ($amountCents < 1) {
            throw ValidationException::withMessages(['amount_cents' => 'El importe debe ser mayor que cero.']);
        }
        $isExtraordinary = (bool) $data['is_extraordinary'];
        $justification = trim((string) ($data['extraordinary_justification'] ?? ''));
        if ($isExtraordinary && mb_strlen($justification) < 10) {
            throw ValidationException::withMessages(['extraordinary_justification' => 'Justifica la comisión extraordinaria.']);
        }
        $date = CarbonImmutable::parse($data['commission_date']);
        if ($date->year !== $fund->budget->fiscal_year) {
            throw ValidationException::withMessages(['commission_date' => 'La fecha debe pertenecer al ejercicio del fondo.']);
        }
        $plannedNeedId = $data['own_revenue_proposal_fuel_need_id'] ?? null;
        if ($plannedNeedId !== null && ! OwnRevenueProposalFuelNeed::query()
            ->where('own_revenue_budget_id', $fund->own_revenue_budget_id)->whereKey($plannedNeedId)->exists()) {
            throw ValidationException::withMessages(['own_revenue_proposal_fuel_need_id' => 'La necesidad planeada no pertenece a este ejercicio.']);
        }

        return DB::transaction(function () use ($fund, $user, $data, $date, $amountCents, $litersScaled, $isExtraordinary, $justification, $plannedNeedId): OwnRevenueFuelCommission {
            $lockedFund = OwnRevenueFuelFund::query()->lockForUpdate()->findOrFail($fund->id);
            Gate::forUser($user)->authorize('manageFuelOperations', $lockedFund->budget);

            return $lockedFund->commissions()->create([
                'own_revenue_proposal_fuel_need_id' => $plannedNeedId,
                'status' => OwnRevenueFuelCommissionStatus::Pending,
                'commission_date' => $date->toDateString(),
                'reason' => trim($data['reason']),
                'route_description' => trim($data['route_description']),
                'vehicle_description' => trim($data['vehicle_description']),
                'kilometers' => $data['kilometers'],
                'liters' => $data['liters'],
                'amount_cents' => $amountCents,
                'effective_price_per_liter' => bcdiv((string) ($amountCents * 100), (string) $litersScaled, 4),
                'is_extraordinary' => $isExtraordinary,
                'extraordinary_justification' => $isExtraordinary ? $justification : null,
                'created_by' => $user->id,
            ]);
        }, attempts: 3);
    }

    private function positiveDecimalScaled(string $value, string $field): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', trim($value), $matches)) {
            throw ValidationException::withMessages([$field => 'Captura un valor válido con máximo cuatro decimales.']);
        }
        $scaled = ((int) $matches[1] * 10_000) + (int) str_pad($matches[2] ?? '', 4, '0');
        if ($scaled < 1) {
            throw ValidationException::withMessages([$field => 'El valor debe ser mayor que cero.']);
        }

        return $scaled;
    }
}
