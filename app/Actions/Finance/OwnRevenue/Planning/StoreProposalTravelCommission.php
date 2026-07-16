<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\TravelCommissionCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProposalTravelCommission
{
    public function __construct(
        private readonly FixedDecimal $decimal,
        private readonly PortableIntegerAmount $amounts,
        private readonly TravelCommissionCalculator $calculator,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(OwnRevenueProposal $proposal, User $user, array $data, ?OwnRevenueProposalTravelCommission $commission = null): OwnRevenueProposalTravelCommission
    {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);

        return DB::transaction(function () use ($proposal, $user, $data, $commission): OwnRevenueProposalTravelCommission {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            $lockedCommission = $commission === null ? null : OwnRevenueProposalTravelCommission::query()->lockForUpdate()->findOrFail($commission->id);
            if ($lockedCommission !== null && $lockedCommission->own_revenue_proposal_id !== $lockedProposal->id) {
                abort(404);
            }
            $activity = OwnRevenueActivity::query()->whereBelongsTo($lockedProposal->budget, 'budget')->find($data['own_revenue_activity_id']);
            if ($activity === null) {
                throw ValidationException::withMessages(['own_revenue_activity_id' => 'Selecciona una actividad de este presupuesto.']);
            }
            $destination = OwnRevenueTravelDestination::query()
                ->whereBelongsTo($lockedProposal->budget, 'budget')->where('is_active', true)
                ->find($data['own_revenue_travel_destination_id']);
            if ($destination === null) {
                throw ValidationException::withMessages(['own_revenue_travel_destination_id' => 'Selecciona un destino activo de este presupuesto.']);
            }
            $umaValue = $this->decimal->requireNonNegative($data['uma_value'] ?? (string) $lockedProposal->budget->uma_value);
            $foodZone = (int) ($data['food_zone'] ?? $destination->food_zone);
            $lodgingZone = (int) ($data['lodging_zone'] ?? $destination->lodging_zone);
            $flightAmount = $this->normalizeAmount((string) ($data['flight_amount_cents'] ?? 0));
            $justification = trim((string) ($data['override_justification'] ?? ''));
            $overrides = [];
            if ($foodZone !== $destination->food_zone) {
                $overrides[] = ['field' => 'food_zone', 'old' => (string) $destination->food_zone, 'new' => (string) $foodZone];
            }
            if ($lodgingZone !== $destination->lodging_zone) {
                $overrides[] = ['field' => 'lodging_zone', 'old' => (string) $destination->lodging_zone, 'new' => (string) $lodgingZone];
            }
            if ($this->decimal->compare((string) $lockedProposal->budget->uma_value, $umaValue) !== 0) {
                $overrides[] = ['field' => 'uma_value', 'old' => (string) $lockedProposal->budget->uma_value, 'new' => $umaValue];
            }
            if ($overrides !== [] && $justification === '') {
                throw ValidationException::withMessages(['override_justification' => 'Explica las diferencias respecto del destino o la UMA del presupuesto.']);
            }
            $attributes = [
                'own_revenue_budget_id' => $lockedProposal->own_revenue_budget_id,
                'own_revenue_activity_id' => $activity->id,
                'own_revenue_travel_destination_id' => $destination->id,
                'commission_date_label' => $data['commission_date_label'] ?? null,
                'operational_month' => $data['operational_month'],
                'budget_month' => $data['budget_month'],
                'reason' => $data['reason'],
                'destination' => $destination->destination,
                'food_zone' => $foodZone,
                'lodging_zone' => $lodgingZone,
                'uma_value' => $umaValue,
                'flight_amount_cents' => $flightAmount,
                'override_justification' => $overrides === [] ? null : $justification,
                'sort_order' => $data['sort_order'] ?? 0,
            ];
            if ($lockedCommission === null) {
                $lockedCommission = $lockedProposal->travelCommissions()->create([
                    ...$attributes,
                    'stable_key' => 'manual:'.Str::uuid(),
                    'participants_amount_cents' => 0,
                    'total_amount_cents' => $flightAmount,
                ]);
            } else {
                $lockedCommission->update($attributes);
                $this->recalculateParticipantSnapshots($lockedCommission);
            }
            foreach ($overrides as $override) {
                $lockedCommission->corrections()->create([
                    'own_revenue_proposal_id' => $lockedProposal->id,
                    'field' => $override['field'], 'old_value' => $override['old'], 'new_value' => $override['new'],
                    'justification' => $justification, 'corrected_by' => $user->id, 'corrected_at' => now(),
                ]);
            }
            $this->recalculateTotals($lockedCommission, $lockedProposal);

            return $lockedCommission->refresh();
        }, attempts: 3);
    }

    private function normalizeAmount(string $amount): string
    {
        if (! $this->amounts->isValid($amount)) {
            throw ValidationException::withMessages(['flight_amount_cents' => 'El importe de transporte aéreo no es válido.']);
        }

        return $this->amounts->normalize($amount);
    }

    private function recalculateParticipantSnapshots(OwnRevenueProposalTravelCommission $commission): void
    {
        foreach ($commission->participants()->lockForUpdate()->get() as $participant) {
            $result = $this->calculator->calculate(
                (string) $participant->commission_days,
                (string) $participant->per_diem_uma,
                (string) $participant->lodging_uma,
                (string) $commission->uma_value,
                '0',
            );
            $participant->update([
                'own_revenue_activity_id' => $commission->own_revenue_activity_id,
                'per_diem_amount_cents' => $result->perDiemCents,
                'lodging_amount_cents' => $result->lodgingCents,
                'total_amount_cents' => $result->totalCents,
            ]);
        }
    }

    private function recalculateTotals(OwnRevenueProposalTravelCommission $commission, OwnRevenueProposal $proposal): void
    {
        $participants = $commission->participants()->sum('total_amount_cents');
        $commission->update(['participants_amount_cents' => $participants, 'total_amount_cents' => $participants + $commission->flight_amount_cents]);
        $proposal->update(['total_amount_cents' => $proposal->technicalNeeds()->sum('budget_amount_cents')
            + $proposal->fuelNeeds()->sum('budget_amount_cents') + $proposal->travelCommissions()->sum('total_amount_cents')]);
    }
}
