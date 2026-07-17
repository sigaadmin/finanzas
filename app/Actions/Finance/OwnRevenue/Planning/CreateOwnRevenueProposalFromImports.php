<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\FuelNeedCalculator;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalProjector;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;
use App\Services\Finance\OwnRevenue\Planning\ProportionalAmountAllocator;
use App\Services\Finance\OwnRevenue\Planning\TravelCommissionCalculator;
use Brick\Math\BigInteger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOwnRevenueProposalFromImports
{
    public function __construct(
        private readonly OwnRevenueProposalReadiness $readiness,
        private readonly FixedDecimal $decimal,
        private readonly FuelNeedCalculator $fuelCalculator,
        private readonly TravelCommissionCalculator $travelCalculator,
        private readonly OwnRevenueProposalProjector $projector,
        private readonly ProportionalAmountAllocator $allocator,
    ) {}

    /** @param array<string, int> $expectedFileIds */
    public function handle(
        OwnRevenueBudget $budget,
        User $user,
        array $expectedFileIds,
        string $expectedFingerprint,
    ): OwnRevenueProposal {
        Gate::forUser($user)->authorize('createProposal', $budget);

        return DB::transaction(function () use ($budget, $user, $expectedFileIds, $expectedFingerprint): OwnRevenueProposal {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('createProposal', $lockedBudget);
            OwnRevenueImportFile::query()->whereBelongsTo($lockedBudget, 'budget')->lockForUpdate()->get();
            $readiness = $this->readiness->forBudget($lockedBudget);

            if (! $readiness->ready
                || ! hash_equals($readiness->fingerprint, $expectedFingerprint)
                || $readiness->fileIds !== $expectedFileIds) {
                throw ValidationException::withMessages([
                    'source_fingerprint' => $readiness->blockers[0] ?? 'Las importaciones cambiaron; actualiza la pantalla antes de crear la propuesta.',
                ]);
            }

            $existing = $this->existingDraft($lockedBudget, $expectedFileIds, $expectedFingerprint);
            if ($existing !== null) {
                return $existing;
            }

            $proposal = OwnRevenueProposal::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'version_number' => ((int) $lockedBudget->proposals()->lockForUpdate()->max('version_number')) + 1,
                'status' => OwnRevenueProposalStatus::Draft,
                'source_abpre_file_id' => $expectedFileIds[OwnRevenueImportFormat::Abpre->value],
                'source_work_sheet_file_id' => $expectedFileIds[OwnRevenueImportFormat::WorkSheet->value],
                'source_technical_sheet_file_id' => $expectedFileIds[OwnRevenueImportFormat::TechnicalSheet->value],
                'source_fuel_file_id' => $expectedFileIds[OwnRevenueImportFormat::Fuel->value],
                'source_travel_expenses_file_id' => $expectedFileIds[OwnRevenueImportFormat::TravelExpenses->value],
                'source_fingerprint' => $expectedFingerprint,
                'created_by' => $user->id,
            ]);

            $this->copyTechnicalNeeds($proposal);
            $this->copyFuelNeeds($proposal);
            $this->copyTravelCommissions($proposal);
            $this->copyWorkSheetResiduals($proposal);
            $proposal->update([
                'total_amount_cents' => $proposal->technicalNeeds()->sum('budget_amount_cents')
                    + $proposal->fuelNeeds()->sum('budget_amount_cents')
                    + $proposal->travelCommissions()->sum('total_amount_cents'),
            ]);

            return $proposal->refresh();
        }, attempts: 3);
    }

    /** @param array<string, int> $fileIds */
    private function existingDraft(OwnRevenueBudget $budget, array $fileIds, string $fingerprint): ?OwnRevenueProposal
    {
        return $budget->proposals()
            ->where('status', OwnRevenueProposalStatus::Draft)
            ->where('source_fingerprint', $fingerprint)
            ->where('source_abpre_file_id', $fileIds[OwnRevenueImportFormat::Abpre->value])
            ->where('source_work_sheet_file_id', $fileIds[OwnRevenueImportFormat::WorkSheet->value])
            ->where('source_technical_sheet_file_id', $fileIds[OwnRevenueImportFormat::TechnicalSheet->value])
            ->where('source_fuel_file_id', $fileIds[OwnRevenueImportFormat::Fuel->value])
            ->where('source_travel_expenses_file_id', $fileIds[OwnRevenueImportFormat::TravelExpenses->value])
            ->first();
    }

    private function copyTechnicalNeeds(OwnRevenueProposal $proposal): void
    {
        OwnRevenueTechnicalSheetNeed::query()
            ->where('own_revenue_import_file_id', $proposal->source_technical_sheet_file_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (OwnRevenueTechnicalSheetNeed $source) use ($proposal): void {
                $proposal->technicalNeeds()->create([
                    'own_revenue_budget_id' => $proposal->own_revenue_budget_id,
                    'own_revenue_activity_id' => $source->own_revenue_activity_id,
                    'source_technical_sheet_need_id' => $source->id,
                    'expense_classification_id' => $source->expense_classification_id,
                    'stable_key' => 'technical-sheet:'.$source->id,
                    'specific_item_code' => $source->specific_item_code,
                    'specific_item_name' => $source->specific_item_name,
                    'chapter_code' => $source->chapter_code,
                    'chapter_name' => $source->chapter_name,
                    'sequence' => $source->sequence,
                    'quantity' => $source->quantity,
                    'unit' => $source->unit,
                    'description' => $source->description,
                    'reference_amount_cents' => $source->amount_cents,
                    'budget_amount_cents' => $source->amount_cents,
                    'budget_month' => $source->budget_month,
                    'region_code' => '02-001',
                    'region_name' => 'Felipe Carrillo Puerto',
                    'sort_order' => $source->sort_order,
                ]);
            });
    }

    private function copyFuelNeeds(OwnRevenueProposal $proposal): void
    {
        OwnRevenueFuelPlan::query()
            ->where('own_revenue_import_file_id', $proposal->source_fuel_file_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (OwnRevenueFuelPlan $source) use ($proposal): void {
                $route = $proposal->budget->planningRoutes()->firstOrCreate([
                    'normalized_origin' => $this->normalize($source->outbound_origin),
                    'normalized_destination' => $this->normalize($source->outbound_destination),
                ], [
                    'origin' => $source->outbound_origin,
                    'destination' => $source->outbound_destination,
                    'one_way_kilometers' => $source->outbound_kilometers,
                    'additional_kilometers' => '0.0000',
                    'is_active' => true,
                ]);
                $returnKilometers = $source->return_kilometers ?? '0';
                $totalKilometers = $this->decimal->add(
                    (string) $source->outbound_kilometers,
                    (string) $returnKilometers,
                    4,
                );
                $calculation = $this->fuelCalculator->calculate(
                    $totalKilometers,
                    (string) $source->kilometers_per_liter,
                    (string) $source->fuel_price,
                );
                $keepsImportedAmount = $calculation->budgetedCents !== (string) $source->amount_cents;

                $proposal->fuelNeeds()->create([
                    'own_revenue_budget_id' => $proposal->own_revenue_budget_id,
                    'own_revenue_activity_id' => $source->own_revenue_activity_id,
                    'source_fuel_plan_id' => $source->id,
                    'own_revenue_route_id' => $route->id,
                    'stable_key' => 'fuel:'.$source->id,
                    'commission_date_label' => $source->commission_date_label,
                    'operational_month' => $source->month,
                    'budget_month' => $proposal->budget->fuel_budget_month,
                    'reason' => $source->reason,
                    'vehicle_model' => $source->vehicle_model,
                    'kilometers_per_liter' => $source->kilometers_per_liter ?? '0',
                    'outbound_origin' => $source->outbound_origin,
                    'outbound_destination' => $source->outbound_destination,
                    'outbound_kilometers' => $source->outbound_kilometers,
                    'return_origin' => $source->return_origin,
                    'return_destination' => $source->return_destination,
                    'return_kilometers' => $returnKilometers,
                    'additional_kilometers' => '0.0000',
                    'total_kilometers' => $totalKilometers,
                    'liters' => $calculation->liters,
                    'fuel_price' => $source->fuel_price,
                    'mathematical_amount_cents' => $calculation->mathematicalCents,
                    'rounded_amount_cents' => $calculation->roundedCents,
                    'budget_amount_cents' => $source->amount_cents,
                    'rounding_difference_cents' => $calculation->roundingDifferenceCents,
                    'override_justification' => $keepsImportedAmount
                        ? 'Importe conservado del archivo confirmado.'
                        : null,
                    'sort_order' => $source->sort_order,
                ]);
            });
    }

    private function copyTravelCommissions(OwnRevenueProposal $proposal): void
    {
        $sources = OwnRevenueTravelCommission::query()
            ->where('own_revenue_import_file_id', $proposal->source_travel_expenses_file_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $sources->groupBy(fn (OwnRevenueTravelCommission $source): string => implode('|', [
            $source->own_revenue_activity_id,
            $this->normalize($source->commission_date_label ?? ''),
            $source->month,
            $this->normalize($source->reason),
            $this->normalize($source->destination),
        ]))->each(function (Collection $group, string $groupKey) use ($proposal): void {
            /** @var OwnRevenueTravelCommission $first */
            $first = $group->first();
            $destination = $proposal->budget->travelDestinations()->firstOrCreate([
                'normalized_destination' => $this->normalize($first->destination),
            ], [
                'destination' => $first->destination,
                'food_zone' => 1,
                'lodging_zone' => 1,
                'is_active' => true,
            ]);
            $flightAmount = (int) $group->sum('flight_amount_cents');
            $commission = $proposal->travelCommissions()->create([
                'own_revenue_budget_id' => $proposal->own_revenue_budget_id,
                'own_revenue_activity_id' => $first->own_revenue_activity_id,
                'source_travel_commission_id' => $first->id,
                'own_revenue_travel_destination_id' => $destination->id,
                'stable_key' => 'travel:'.hash('sha256', $groupKey),
                'commission_date_label' => $first->commission_date_label,
                'operational_month' => $first->month,
                'budget_month' => $first->month,
                'reason' => $first->reason,
                'destination' => $first->destination,
                'food_zone' => 1,
                'lodging_zone' => 1,
                'uma_value' => $first->uma_value,
                'flight_amount_cents' => $flightAmount,
                'participants_amount_cents' => 0,
                'total_amount_cents' => $flightAmount,
                'sort_order' => $first->sort_order,
            ]);

            $group->each(fn (OwnRevenueTravelCommission $source) => $this->copyTravelParticipant($commission, $source));
            $participantsAmount = (int) $commission->participants()->sum('total_amount_cents');
            $commission->update([
                'participants_amount_cents' => $participantsAmount,
                'total_amount_cents' => $participantsAmount + $flightAmount,
            ]);
        });
    }

    private function copyWorkSheetResiduals(OwnRevenueProposal $proposal): void
    {
        $lines = $proposal->sourceWorkSheetFile->workSheetLines()
            ->with(['activity', 'expenseClassification', 'months'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $projected = collect($this->projector->project($proposal)['work_sheet'])->keyBy(
            fn (array $line): string => $this->projectionKey(
                (int) $line['activity_id'],
                (string) $line['specific_item_code'],
                (int) $line['month'],
            ),
        );
        $lodgingTargets = $this->legacyLodgingTargets($proposal, $lines);
        $lodgingClassification = $lodgingTargets === [] ? null : ExpenseClassification::query()
            ->where('fiscal_year', $proposal->budget->fiscal_year)
            ->where('specific_item_code', '37502')
            ->first();
        if ($lodgingTargets !== [] && ! $lodgingClassification instanceof ExpenseClassification) {
            throw ValidationException::withMessages([
                'source_fingerprint' => 'Falta la partida 37502 de hospedaje en el catálogo COG del ejercicio.',
            ]);
        }

        foreach ($lines as $line) {
            foreach ($line->months as $month) {
                $projectedAmount = fn (string $item): BigInteger => BigInteger::of($projected->get(
                    $this->projectionKey($line->own_revenue_activity_id, $item, $month->month),
                    ['amount_cents' => '0'],
                )['amount_cents']);
                $coverage = $projectedAmount($line->specific_item_code);
                $hasSeparateLodging = $line->specific_item_code === '37501'
                    && $lines->contains(fn ($candidate): bool => $candidate->own_revenue_activity_id === $line->own_revenue_activity_id
                        && $candidate->specific_item_code === '37502');
                if ($line->specific_item_code === '37501' && ! $hasSeparateLodging) {
                    $projectedLodging = $projectedAmount('37502');
                    $coverage = $coverage->plus($projectedLodging);
                    $lodgingTarget = BigInteger::of($lodgingTargets[$line->id.'|'.$month->month] ?? '0');
                    if ($lodgingTarget->isGreaterThan($projectedLodging)) {
                        $lodgingResidual = (string) $lodgingTarget->minus($projectedLodging);
                        $this->createWorkSheetResidual(
                            $proposal,
                            $line,
                            $month->month,
                            $lodgingClassification,
                            $lodgingResidual,
                            '37502',
                            $lodgingClassification->specific_item_name,
                            ':lodging',
                        );
                        $coverage = $coverage->plus($lodgingResidual);
                    }
                }
                $target = BigInteger::of((string) $month->getRawOriginal('amount_cents'));
                if (! $target->isGreaterThan($coverage)) {
                    continue;
                }
                $residual = (string) $target->minus($coverage);
                $this->createWorkSheetResidual(
                    $proposal,
                    $line,
                    $month->month,
                    $line->expenseClassification,
                    $residual,
                    $line->specific_item_code,
                    $line->item_name,
                );
            }
        }
    }

    private function createWorkSheetResidual(
        OwnRevenueProposal $proposal,
        object $line,
        int $month,
        ExpenseClassification $classification,
        string $amount,
        string $itemCode,
        string $itemName,
        string $stableKeySuffix = '',
    ): void {
        $proposal->technicalNeeds()->create([
            'own_revenue_budget_id' => $proposal->own_revenue_budget_id,
            'own_revenue_activity_id' => $line->own_revenue_activity_id,
            'expense_classification_id' => $classification->id,
            'stable_key' => 'work-sheet:'.$line->id.':'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).$stableKeySuffix,
            'specific_item_code' => $itemCode,
            'specific_item_name' => $classification->specific_item_name,
            'chapter_code' => $classification->chapter_code,
            'chapter_name' => $classification->chapter_name,
            'quantity' => '1.0000',
            'unit' => 'CONCEPTO',
            'description' => 'Concepto incorporado desde la Hoja de trabajo: '.$itemName,
            'unit_price_cents' => $amount,
            'reference_amount_cents' => $amount,
            'budget_amount_cents' => $amount,
            'budget_month' => $month,
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'sort_order' => ($line->sort_order * 100) + $month,
        ]);
    }

    /** @return array<string, string> */
    private function legacyLodgingTargets(OwnRevenueProposal $proposal, Collection $lines): array
    {
        $lodgingByMonth = [];
        foreach ($proposal->sourceAbpreFile->abpreLines()->where('specific_item_code', '37502')->with('months')->get() as $line) {
            foreach ($line->months as $month) {
                $lodgingByMonth[$month->month] = $this->decimal->add(
                    $lodgingByMonth[$month->month] ?? '0',
                    (string) $month->getRawOriginal('amount_cents'),
                    0,
                );
            }
        }

        $weights = [];
        foreach ($lines->where('specific_item_code', '37501') as $line) {
            $hasSeparateLodging = $lines->contains(fn ($candidate): bool => $candidate->own_revenue_activity_id === $line->own_revenue_activity_id
                && $candidate->specific_item_code === '37502');
            if ($hasSeparateLodging) {
                continue;
            }
            foreach ($line->months as $month) {
                $weights[$month->month][$line->id.'|'.$month->month] = (string) $month->getRawOriginal('amount_cents');
            }
        }

        $targets = [];
        foreach ($lodgingByMonth as $month => $amount) {
            foreach ($this->allocator->allocate($amount, $weights[$month] ?? []) as $key => $target) {
                $targets[$key] = $target;
            }
        }

        return $targets;
    }

    private function projectionKey(int $activityId, string $item, int $month): string
    {
        return $activityId.'|'.$item.'|'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    private function copyTravelParticipant(
        OwnRevenueProposalTravelCommission $commission,
        OwnRevenueTravelCommission $source,
    ): void {
        $rate = OwnRevenueTravelRate::query()->firstOrCreate([
            'own_revenue_budget_id' => $commission->own_revenue_budget_id,
            'normalized_position' => $this->normalize($source->position),
            'food_zone' => 1,
            'lodging_zone' => 1,
        ], [
            'position' => $source->position,
            'per_diem_uma' => $source->per_diem_uma,
            'lodging_uma' => $source->lodging_uma,
            'is_fallback' => $this->normalize($source->position) === 'puestos no considerados en los anteriores',
            'is_active' => true,
        ]);
        $calculation = $this->travelCalculator->calculate(
            (string) $source->commission_days,
            (string) $source->per_diem_uma,
            (string) $source->lodging_uma,
            (string) $commission->uma_value,
            '0',
        );
        $commission->participants()->create([
            'own_revenue_proposal_id' => $commission->own_revenue_proposal_id,
            'own_revenue_budget_id' => $commission->own_revenue_budget_id,
            'own_revenue_activity_id' => $commission->own_revenue_activity_id,
            'source_travel_commission_id' => $source->id,
            'own_revenue_travel_rate_id' => $rate->id,
            'stable_key' => 'travel-participant:'.$source->id,
            'person_name' => $source->person_name,
            'position' => $source->position,
            'commission_days' => $source->commission_days,
            'per_diem_uma' => $source->per_diem_uma,
            'lodging_uma' => $source->lodging_uma,
            'per_diem_amount_cents' => $calculation->perDiemCents,
            'lodging_amount_cents' => $calculation->lodgingCents,
            'total_amount_cents' => $calculation->totalCents,
            'sort_order' => $source->sort_order,
        ]);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }
}
