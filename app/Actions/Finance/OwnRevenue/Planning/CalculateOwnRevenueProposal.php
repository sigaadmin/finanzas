<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\FuelNeedCalculator;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalFingerprint;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalProjector;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;
use App\Services\Finance\OwnRevenue\Planning\TravelCommissionCalculator;
use DivisionByZeroError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

class CalculateOwnRevenueProposal
{
    public function __construct(
        private readonly OwnRevenueProposalReadiness $readiness,
        private readonly OwnRevenueProposalFingerprint $fingerprint,
        private readonly OwnRevenueProposalProjector $projector,
        private readonly FuelNeedCalculator $fuelCalculator,
        private readonly TravelCommissionCalculator $travelCalculator,
        private readonly FixedDecimal $decimal,
    ) {}

    public function handle(
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        User $user,
        string $expectedFingerprint,
    ): OwnRevenueProposal {
        Gate::forUser($user)->authorize('calculateProposal', $budget);

        return DB::transaction(function () use ($budget, $proposal, $user, $expectedFingerprint): OwnRevenueProposal {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($lockedProposal->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            Gate::forUser($user)->authorize('calculateProposal', $lockedBudget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }

            OwnRevenueImportFile::query()->whereBelongsTo($lockedBudget, 'budget')->lockForUpdate()->get();
            $this->lockPlanningRows($lockedProposal);
            $lockedProposal->unsetRelations();
            $lockedProposal->setRelation('budget', $lockedBudget);

            $this->validateSources($lockedBudget, $lockedProposal);
            $actualFingerprint = $this->fingerprint->forProposal($lockedProposal);
            if (! hash_equals($actualFingerprint, $expectedFingerprint)) {
                throw ValidationException::withMessages([
                    'proposal_fingerprint' => 'La propuesta cambió; actualiza la página antes de calcularla.',
                ]);
            }

            try {
                $this->validatePlanningRows($lockedBudget, $lockedProposal);
                $projection = $this->projector->project($lockedProposal);
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (DivisionByZeroError|InvalidArgumentException|OverflowException) {
                throw ValidationException::withMessages([
                    'proposal' => 'La propuesta contiene datos que deben corregirse antes de calcularla.',
                ]);
            }

            if ((string) $lockedProposal->getRawOriginal('total_amount_cents') !== $projection['total_amount_cents']) {
                throw ValidationException::withMessages([
                    'proposal' => 'El total guardado no coincide con el detalle de la propuesta.',
                ]);
            }

            $lockedProposal->update([
                'status' => OwnRevenueProposalStatus::Calculated,
                'total_amount_cents' => $projection['total_amount_cents'],
                'calculated_by' => $user->id,
                'calculated_at' => now(),
            ]);
            $lockedBudget->update(['status' => OwnRevenueBudgetStatus::ProposalCalculated]);

            return $lockedProposal->refresh();
        }, attempts: 3);
    }

    private function lockPlanningRows(OwnRevenueProposal $proposal): void
    {
        OwnRevenueProposalTechnicalNeed::query()->whereBelongsTo($proposal, 'proposal')->lockForUpdate()->get();
        OwnRevenueProposalFuelNeed::query()->whereBelongsTo($proposal, 'proposal')->lockForUpdate()->get();
        $commissionIds = OwnRevenueProposalTravelCommission::query()
            ->whereBelongsTo($proposal, 'proposal')->lockForUpdate()->pluck('id');
        OwnRevenueProposalTravelParticipant::query()
            ->whereIn('own_revenue_proposal_travel_commission_id', $commissionIds)->lockForUpdate()->get();
    }

    private function validateSources(OwnRevenueBudget $budget, OwnRevenueProposal $proposal): void
    {
        $readiness = $this->readiness->forBudget($budget);
        $proposalFileIds = [
            OwnRevenueImportFormat::Abpre->value => $proposal->source_abpre_file_id,
            OwnRevenueImportFormat::WorkSheet->value => $proposal->source_work_sheet_file_id,
            OwnRevenueImportFormat::TechnicalSheet->value => $proposal->source_technical_sheet_file_id,
            OwnRevenueImportFormat::Fuel->value => $proposal->source_fuel_file_id,
            OwnRevenueImportFormat::TravelExpenses->value => $proposal->source_travel_expenses_file_id,
        ];
        if (! $readiness->ready
            || ! hash_equals($readiness->fingerprint, $proposal->source_fingerprint)
            || $readiness->fileIds !== $proposalFileIds) {
            throw ValidationException::withMessages([
                'proposal' => $readiness->blockers[0] ?? 'Las importaciones cambiaron; crea una propuesta nueva con la información vigente.',
            ]);
        }
    }

    private function validatePlanningRows(OwnRevenueBudget $budget, OwnRevenueProposal $proposal): void
    {
        if ($budget->region_code !== '02-001' || $budget->region_name !== 'Felipe Carrillo Puerto') {
            $this->invalid('La región del presupuesto debe ser 02-001, Felipe Carrillo Puerto.');
        }
        $proposal->loadMissing([
            'technicalNeeds.expenseClassification',
            'fuelNeeds',
            'travelCommissions.participants',
        ]);
        if ($proposal->technicalNeeds->isEmpty()
            && $proposal->fuelNeeds->isEmpty()
            && $proposal->travelCommissions->isEmpty()) {
            $this->invalid('Agrega al menos un concepto antes de calcular la propuesta.');
        }

        foreach ($proposal->technicalNeeds as $need) {
            $this->validateCommonReferences($budget, $need->own_revenue_activity_id);
            $classification = $need->expenseClassification;
            if (! $classification instanceof ExpenseClassification
                || $classification->fiscal_year !== $budget->fiscal_year
                || $classification->specific_item_code !== $need->specific_item_code) {
                $this->invalid('Una necesidad técnica no coincide con el catálogo COG vigente.');
            }
            if ($need->region_code !== '02-001' || $need->region_name !== 'Felipe Carrillo Puerto') {
                $this->invalid('La región de todas las necesidades técnicas debe ser 02-001, Felipe Carrillo Puerto.');
            }
            $this->validateMonth($need->budget_month);
            if ($need->unit_price_cents !== null) {
                $expectedReference = $this->decimal->roundHalfUp(
                    $this->decimal->multiply((string) $need->quantity, (string) $need->unit_price_cents, 8),
                    0,
                );
                if ($expectedReference !== (string) $need->reference_amount_cents) {
                    $this->invalid('Una necesidad técnica tiene un importe de referencia desactualizado.');
                }
            }
        }

        foreach ($proposal->fuelNeeds as $need) {
            $this->validateCommonReferences($budget, $need->own_revenue_activity_id);
            if (! OwnRevenueRoute::query()->whereBelongsTo($budget, 'budget')->whereKey($need->own_revenue_route_id)->exists()) {
                $this->invalid('Un registro de combustible usa un recorrido de otro presupuesto.');
            }
            $this->validateMonth($need->operational_month);
            if ($need->budget_month !== $budget->fuel_budget_month) {
                $this->invalid('El mes presupuestal de combustible no coincide con la configuración anual.');
            }
            $total = $this->decimal->add(
                $this->decimal->add((string) $need->outbound_kilometers, (string) $need->return_kilometers, 4),
                (string) $need->additional_kilometers,
                4,
            );
            $result = $this->fuelCalculator->calculate($total, (string) $need->kilometers_per_liter, (string) $need->fuel_price);
            if ($this->decimal->compare($total, (string) $need->total_kilometers) !== 0
                || $this->decimal->compare($result->liters, (string) $need->liters) !== 0
                || $result->mathematicalCents !== (string) $need->mathematical_amount_cents
                || $result->roundedCents !== (string) $need->rounded_amount_cents
                || $result->roundingDifferenceCents !== (string) $need->rounding_difference_cents) {
                $this->invalid('Un registro de combustible tiene cálculos desactualizados.');
            }
            if ($result->budgetedCents !== (string) $need->budget_amount_cents && blank($need->override_justification)) {
                $this->invalid('Explica el importe de combustible que difiere del cálculo automático.');
            }
        }

        foreach ($proposal->travelCommissions as $commission) {
            $this->validateCommonReferences($budget, $commission->own_revenue_activity_id);
            if (! OwnRevenueTravelDestination::query()->whereBelongsTo($budget, 'budget')->whereKey($commission->own_revenue_travel_destination_id)->exists()) {
                $this->invalid('Una comisión usa un destino de otro presupuesto.');
            }
            $this->validateMonth($commission->operational_month);
            $this->validateMonth($commission->budget_month);
            $participantsTotal = '0';
            foreach ($commission->participants as $participant) {
                if ($participant->own_revenue_proposal_id !== $proposal->id
                    || $participant->own_revenue_budget_id !== $budget->id
                    || $participant->own_revenue_activity_id !== $commission->own_revenue_activity_id
                    || ! OwnRevenueTravelRate::query()->whereBelongsTo($budget, 'budget')->whereKey($participant->own_revenue_travel_rate_id)->exists()) {
                    $this->invalid('Una persona comisionada contiene referencias que no corresponden a la propuesta.');
                }
                $result = $this->travelCalculator->calculate(
                    (string) $participant->commission_days,
                    (string) $participant->per_diem_uma,
                    (string) $participant->lodging_uma,
                    (string) $commission->uma_value,
                    '0',
                );
                if ($result->perDiemCents !== (string) $participant->per_diem_amount_cents
                    || $result->lodgingCents !== (string) $participant->lodging_amount_cents
                    || $result->totalCents !== (string) $participant->total_amount_cents) {
                    $this->invalid('Una persona comisionada tiene cálculos desactualizados.');
                }
                $participantsTotal = $this->decimal->add(
                    $participantsTotal,
                    (string) $participant->total_amount_cents,
                    0,
                );
            }
            $commissionTotal = $this->decimal->add($participantsTotal, (string) $commission->flight_amount_cents, 0);
            if ($participantsTotal !== (string) $commission->participants_amount_cents
                || $commissionTotal !== (string) $commission->total_amount_cents) {
                $this->invalid('El total de una comisión no coincide con sus personas y transporte aéreo.');
            }
        }

        $requiredItems = [];
        if ($proposal->fuelNeeds->isNotEmpty()) {
            $requiredItems[] = '26101';
        }
        if ($proposal->travelCommissions->isNotEmpty()) {
            $requiredItems[] = '37501';
        }
        if ($proposal->travelCommissions->contains(fn ($commission): bool => $commission->flight_amount_cents > 0)) {
            $requiredItems[] = '37101';
        }
        foreach ($requiredItems as $item) {
            if (! ExpenseClassification::query()->where('fiscal_year', $budget->fiscal_year)->where('specific_item_code', $item)->exists()) {
                $this->invalid("Falta la partida {$item} en el catálogo COG del ejercicio.");
            }
        }
    }

    private function validateCommonReferences(OwnRevenueBudget $budget, int $activityId): void
    {
        if (! OwnRevenueActivity::query()->whereBelongsTo($budget, 'budget')->whereKey($activityId)->exists()) {
            $this->invalid('Una fila usa una actividad de otro presupuesto.');
        }
    }

    private function validateMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            $this->invalid('Todos los meses deben estar entre enero y diciembre.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['proposal' => $message]);
    }
}
