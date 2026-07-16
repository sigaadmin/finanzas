<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\TravelCommissionCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProposalTravelParticipant
{
    public function __construct(
        private readonly TravelCommissionCalculator $calculator,
        private readonly FixedDecimal $decimal,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        OwnRevenueProposal $proposal,
        OwnRevenueProposalTravelCommission $commission,
        User $user,
        array $data,
        ?OwnRevenueProposalTravelParticipant $participant = null,
    ): OwnRevenueProposalTravelParticipant {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);

        return DB::transaction(function () use ($proposal, $commission, $user, $data, $participant): OwnRevenueProposalTravelParticipant {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $lockedCommission = OwnRevenueProposalTravelCommission::query()->lockForUpdate()->findOrFail($commission->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            if ($lockedCommission->own_revenue_proposal_id !== $lockedProposal->id) {
                abort(404);
            }
            $lockedParticipant = $participant === null ? null : OwnRevenueProposalTravelParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($lockedParticipant !== null && $lockedParticipant->own_revenue_proposal_travel_commission_id !== $lockedCommission->id) {
                abort(404);
            }
            $position = Str::squish($data['position']);
            $normalizedPosition = Str::lower($position);
            $rateQuery = OwnRevenueTravelRate::query()
                ->whereBelongsTo($lockedProposal->budget, 'budget')
                ->where('food_zone', $lockedCommission->food_zone)
                ->where('lodging_zone', $lockedCommission->lodging_zone)
                ->where('is_active', true);
            $rate = (clone $rateQuery)->where('normalized_position', $normalizedPosition)->first()
                ?? (clone $rateQuery)->where('is_fallback', true)->first();
            if ($rate === null) {
                throw ValidationException::withMessages(['position' => 'No existe una tarifa activa para el cargo y las zonas de esta comisión.']);
            }
            $perDiemUma = $this->decimal->requireNonNegative($data['per_diem_uma'] ?? (string) $rate->per_diem_uma);
            $lodgingUma = $this->decimal->requireNonNegative($data['lodging_uma'] ?? (string) $rate->lodging_uma);
            $commissionDays = $this->decimal->requireNonNegative($data['commission_days']);
            $justification = trim((string) ($data['override_justification'] ?? ''));
            $overrides = [];
            if ($this->decimal->compare((string) $rate->per_diem_uma, $perDiemUma) !== 0) {
                $overrides[] = ['field' => 'per_diem_uma', 'old' => (string) $rate->per_diem_uma, 'new' => $perDiemUma];
            }
            if ($this->decimal->compare((string) $rate->lodging_uma, $lodgingUma) !== 0) {
                $overrides[] = ['field' => 'lodging_uma', 'old' => (string) $rate->lodging_uma, 'new' => $lodgingUma];
            }
            if ($overrides !== [] && $justification === '') {
                throw ValidationException::withMessages(['override_justification' => 'Explica por qué las tarifas difieren del catálogo.']);
            }
            $result = $this->calculator->calculate($commissionDays, $perDiemUma, $lodgingUma, (string) $lockedCommission->uma_value, '0');
            $attributes = [
                'own_revenue_proposal_id' => $lockedProposal->id,
                'own_revenue_budget_id' => $lockedProposal->own_revenue_budget_id,
                'own_revenue_activity_id' => $lockedCommission->own_revenue_activity_id,
                'own_revenue_travel_rate_id' => $rate->id,
                'person_name' => Str::squish($data['person_name']),
                'position' => $position,
                'commission_days' => $commissionDays,
                'per_diem_uma' => $perDiemUma,
                'lodging_uma' => $lodgingUma,
                'per_diem_amount_cents' => $result->perDiemCents,
                'lodging_amount_cents' => $result->lodgingCents,
                'total_amount_cents' => $result->totalCents,
                'sort_order' => $data['sort_order'] ?? 0,
            ];
            if ($lockedParticipant === null) {
                $lockedParticipant = $lockedCommission->participants()->create([...$attributes, 'stable_key' => 'manual:'.Str::uuid()]);
            } else {
                $lockedParticipant->update($attributes);
            }
            foreach ($overrides as $override) {
                $lockedParticipant->corrections()->create([
                    'own_revenue_proposal_id' => $lockedProposal->id,
                    'field' => $override['field'], 'old_value' => $override['old'], 'new_value' => $override['new'],
                    'justification' => $justification, 'corrected_by' => $user->id, 'corrected_at' => now(),
                ]);
            }
            $this->recalculateTotals($lockedCommission, $lockedProposal);

            return $lockedParticipant->refresh();
        }, attempts: 3);
    }

    private function recalculateTotals(OwnRevenueProposalTravelCommission $commission, OwnRevenueProposal $proposal): void
    {
        $participants = $commission->participants()->sum('total_amount_cents');
        $commission->update(['participants_amount_cents' => $participants, 'total_amount_cents' => $participants + $commission->flight_amount_cents]);
        $proposal->update(['total_amount_cents' => $proposal->technicalNeeds()->sum('budget_amount_cents')
            + $proposal->fuelNeeds()->sum('budget_amount_cents') + $proposal->travelCommissions()->sum('total_amount_cents')]);
    }
}
