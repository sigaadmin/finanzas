<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\FuelNeedCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProposalFuelNeed
{
    public function __construct(
        private readonly FuelNeedCalculator $calculator,
        private readonly FixedDecimal $decimal,
        private readonly PortableIntegerAmount $amounts,
    ) {}

    public function handle(
        OwnRevenueProposal $proposal,
        User $user,
        FuelNeedData $data,
        ?OwnRevenueProposalFuelNeed $need = null,
    ): OwnRevenueProposalFuelNeed {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);

        return DB::transaction(function () use ($proposal, $user, $data, $need): OwnRevenueProposalFuelNeed {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            $lockedNeed = $need === null
                ? null
                : OwnRevenueProposalFuelNeed::query()->lockForUpdate()->findOrFail($need->id);
            if ($lockedNeed !== null && $lockedNeed->own_revenue_proposal_id !== $lockedProposal->id) {
                abort(404);
            }

            $activity = OwnRevenueActivity::query()
                ->whereBelongsTo($lockedProposal->budget, 'budget')
                ->find($data->activityId);
            if ($activity === null) {
                throw ValidationException::withMessages([
                    'own_revenue_activity_id' => 'Selecciona una actividad de este presupuesto.',
                ]);
            }
            $route = OwnRevenueRoute::query()
                ->whereBelongsTo($lockedProposal->budget, 'budget')
                ->where('is_active', true)
                ->find($data->routeId);
            if ($route === null) {
                throw ValidationException::withMessages([
                    'own_revenue_route_id' => 'Selecciona un recorrido activo de este presupuesto.',
                ]);
            }

            $expectedOutbound = (string) $route->one_way_kilometers;
            $expectedReturn = (string) $route->one_way_kilometers;
            $expectedAdditional = (string) $route->additional_kilometers;
            $outboundKilometers = $this->decimal->requireNonNegative($data->outboundKilometers ?? $expectedOutbound);
            $returnKilometers = $this->decimal->requireNonNegative($data->returnKilometers ?? $expectedReturn);
            $additionalKilometers = $this->decimal->requireNonNegative($data->additionalKilometers ?? $expectedAdditional);
            $expectedTotal = $this->sumKilometers($expectedOutbound, $expectedReturn, $expectedAdditional);
            $totalKilometers = $this->sumKilometers($outboundKilometers, $returnKilometers, $additionalKilometers);
            $outboundOrigin = Str::squish($data->outboundOrigin ?? $route->origin);
            $outboundDestination = Str::squish($data->outboundDestination ?? $route->destination);
            $returnOrigin = Str::squish($data->returnOrigin ?? $route->destination);
            $returnDestination = Str::squish($data->returnDestination ?? $route->origin);
            $fuelPrice = $this->decimal->requireNonNegative(
                $data->fuelPrice ?? (string) $lockedProposal->budget->fuel_price_per_liter,
            );
            $kilometersPerLiter = $this->decimal->requireNonNegative($data->kilometersPerLiter);
            $calculation = $this->calculator->calculate($totalKilometers, $kilometersPerLiter, $fuelPrice);
            $budgetAmountCents = $data->budgetAmountCents === null
                ? $calculation->budgetedCents
                : $this->normalizedAmount($data->budgetAmountCents);
            $justification = trim((string) $data->overrideJustification);

            $overrides = $this->overrides(
                $lockedProposal,
                $lockedNeed,
                $route,
                $expectedTotal,
                $totalKilometers,
                $outboundOrigin,
                $outboundDestination,
                $kilometersPerLiter,
                $fuelPrice,
                $calculation->budgetedCents,
                $budgetAmountCents,
            );
            if ($overrides !== [] && $justification === '') {
                throw ValidationException::withMessages([
                    'override_justification' => 'Explica las diferencias respecto de los valores calculados o catalogados.',
                ]);
            }

            $attributes = [
                'own_revenue_budget_id' => $lockedProposal->own_revenue_budget_id,
                'own_revenue_activity_id' => $activity->id,
                'own_revenue_route_id' => $route->id,
                'commission_date_label' => $data->commissionDateLabel,
                'operational_month' => $data->operationalMonth,
                'budget_month' => $lockedProposal->budget->fuel_budget_month,
                'reason' => $data->reason,
                'vehicle_model' => $data->vehicleModel,
                'kilometers_per_liter' => $kilometersPerLiter,
                'outbound_origin' => $outboundOrigin,
                'outbound_destination' => $outboundDestination,
                'outbound_kilometers' => $outboundKilometers,
                'return_origin' => $returnOrigin,
                'return_destination' => $returnDestination,
                'return_kilometers' => $returnKilometers,
                'additional_kilometers' => $additionalKilometers,
                'total_kilometers' => $totalKilometers,
                'liters' => $calculation->liters,
                'fuel_price' => $fuelPrice,
                'mathematical_amount_cents' => $calculation->mathematicalCents,
                'rounded_amount_cents' => $calculation->roundedCents,
                'budget_amount_cents' => $budgetAmountCents,
                'rounding_difference_cents' => $calculation->roundingDifferenceCents,
                'override_justification' => $overrides === [] ? null : $justification,
                'sort_order' => $data->sortOrder,
            ];
            if ($lockedNeed === null) {
                $lockedNeed = $lockedProposal->fuelNeeds()->create([
                    ...$attributes,
                    'stable_key' => 'manual:'.Str::uuid(),
                ]);
            } else {
                $lockedNeed->update($attributes);
            }

            foreach ($overrides as $override) {
                $lockedNeed->corrections()->create([
                    'own_revenue_proposal_id' => $lockedProposal->id,
                    'field' => $override['field'],
                    'old_value' => $override['old'],
                    'new_value' => $override['new'],
                    'justification' => $justification,
                    'corrected_by' => $user->id,
                    'corrected_at' => now(),
                ]);
            }

            $this->recalculateProposalTotal($lockedProposal);

            return $lockedNeed->refresh();
        }, attempts: 3);
    }

    private function sumKilometers(string $outbound, string $return, string $additional): string
    {
        return $this->decimal->add($this->decimal->add($outbound, $return, 4), $additional, 4);
    }

    private function normalizedAmount(string $amount): string
    {
        if (! $this->amounts->isValid($amount)) {
            throw ValidationException::withMessages(['budget_amount_cents' => 'El importe definitivo no es válido.']);
        }

        return $this->amounts->normalize($amount);
    }

    /**
     * @return list<array{field: string, old: string, new: string}>
     */
    private function overrides(
        OwnRevenueProposal $proposal,
        ?OwnRevenueProposalFuelNeed $need,
        OwnRevenueRoute $route,
        string $expectedTotal,
        string $totalKilometers,
        string $outboundOrigin,
        string $outboundDestination,
        string $kilometersPerLiter,
        string $fuelPrice,
        string $calculatedBudgetCents,
        string $budgetAmountCents,
    ): array {
        $overrides = [];
        if ($this->decimal->compare($expectedTotal, $totalKilometers) !== 0) {
            $overrides[] = ['field' => 'total_kilometers', 'old' => $expectedTotal, 'new' => $totalKilometers];
        }
        $expectedPoints = $route->origin.' → '.$route->destination;
        $actualPoints = $outboundOrigin.' → '.$outboundDestination;
        if ($expectedPoints !== $actualPoints) {
            $overrides[] = ['field' => 'route_points', 'old' => $expectedPoints, 'new' => $actualPoints];
        }
        if ($need !== null && $this->decimal->compare((string) $need->kilometers_per_liter, $kilometersPerLiter) !== 0) {
            $overrides[] = [
                'field' => 'kilometers_per_liter',
                'old' => (string) $need->kilometers_per_liter,
                'new' => $kilometersPerLiter,
            ];
        }
        if ($this->decimal->compare((string) $proposal->budget->fuel_price_per_liter, $fuelPrice) !== 0) {
            $overrides[] = [
                'field' => 'fuel_price',
                'old' => (string) $proposal->budget->fuel_price_per_liter,
                'new' => $fuelPrice,
            ];
        }
        if ($calculatedBudgetCents !== $budgetAmountCents) {
            $overrides[] = [
                'field' => 'budget_amount_cents',
                'old' => $calculatedBudgetCents,
                'new' => $budgetAmountCents,
            ];
        }

        return $overrides;
    }

    private function recalculateProposalTotal(OwnRevenueProposal $proposal): void
    {
        $proposal->update([
            'total_amount_cents' => $proposal->technicalNeeds()->sum('budget_amount_cents')
                + $proposal->fuelNeeds()->sum('budget_amount_cents')
                + $proposal->travelCommissions()->sum('total_amount_cents'),
        ]);
    }
}
