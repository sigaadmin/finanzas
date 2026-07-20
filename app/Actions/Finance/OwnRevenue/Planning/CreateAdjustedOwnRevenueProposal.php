<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalProjector;
use App\Services\Finance\OwnRevenue\Planning\ProportionalCutSuggestion;
use Brick\Math\BigInteger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateAdjustedOwnRevenueProposal
{
    public function __construct(
        private readonly OwnRevenueCutReconciliation $reconciliation,
        private readonly ProportionalCutSuggestion $suggestion,
        private readonly OwnRevenueProposalProjector $projector,
    ) {}

    public function handle(
        OwnRevenueBudget $budget,
        OwnRevenueProposal $source,
        User $user,
        string $expectedFingerprint,
    ): OwnRevenueProposal {
        Gate::forUser($user)->authorize('manageProposalCuts', $budget);

        return DB::transaction(function () use ($budget, $source, $user, $expectedFingerprint): OwnRevenueProposal {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedSource = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($source->id);
            if ($lockedSource->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            Gate::forUser($user)->authorize('manageProposalCuts', $lockedBudget);
            if ($lockedSource->status !== OwnRevenueProposalStatus::Calculated) {
                throw new AuthorizationException;
            }
            OwnRevenueImportFile::query()->whereBelongsTo($lockedBudget, 'budget')->lockForUpdate()->get();
            $cuts = OwnRevenueProposalCut::query()->whereBelongsTo($lockedSource, 'proposal')->lockForUpdate()->get();
            $this->lockRows($lockedSource);
            $lockedSource->unsetRelations();
            $lockedSource->setRelation('budget', $lockedBudget);
            $reconciliation = $this->reconciliation->forProposal($lockedSource);
            if (! hash_equals($reconciliation['fingerprint'], $expectedFingerprint) || ! $reconciliation['ready']) {
                throw ValidationException::withMessages([
                    'reconciliation_fingerprint' => $reconciliation['blockers'][0]
                        ?? 'La propuesta o los archivos confirmados cambiaron; actualiza la página.',
                ]);
            }
            if ($reconciliation['summary']['pending_cut_cents'] !== '0'
                || $reconciliation['summary']['adjusted_amount_cents'] !== $reconciliation['summary']['abpre_amount_cents']) {
                throw ValidationException::withMessages([
                    'cuts' => 'Aún falta distribuir parte de las disminuciones requeridas.',
                ]);
            }

            $adjusted = OwnRevenueProposal::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'version_number' => ((int) $lockedBudget->proposals()->lockForUpdate()->max('version_number')) + 1,
                'status' => OwnRevenueProposalStatus::Adjusted,
                'based_on_proposal_id' => $lockedSource->id,
                'source_abpre_file_id' => $lockedSource->source_abpre_file_id,
                'source_work_sheet_file_id' => $lockedSource->source_work_sheet_file_id,
                'source_technical_sheet_file_id' => $lockedSource->source_technical_sheet_file_id,
                'source_fuel_file_id' => $lockedSource->source_fuel_file_id,
                'source_travel_expenses_file_id' => $lockedSource->source_travel_expenses_file_id,
                'source_fingerprint' => $lockedSource->source_fingerprint,
                'total_amount_cents' => $lockedSource->total_amount_cents,
                'created_by' => $user->id,
                'calculated_by' => $user->id,
                'calculated_at' => now(),
            ]);

            $copies = $this->copyRows($lockedSource, $adjusted);
            $this->applyCuts($cuts, $copies);
            $this->applyIncreases($adjusted, $reconciliation['groups']);
            foreach ($adjusted->travelCommissions()->with('participants')->get() as $commission) {
                $participants = $commission->participants->sum('total_amount_cents');
                $commission->update([
                    'participants_amount_cents' => $participants,
                    'total_amount_cents' => $participants + $commission->flight_amount_cents,
                ]);
            }
            $adjusted->unsetRelations();
            $projection = $this->projector->project($adjusted);
            $adjusted->update(['total_amount_cents' => $projection['total_amount_cents']]);
            $adjusted->unsetRelations();
            $adjustedReconciliation = $this->reconciliation->forProposal($adjusted);
            if (! $adjustedReconciliation['ready']
                || $adjustedReconciliation['summary']['required_cut_cents'] !== '0'
                || $adjustedReconciliation['summary']['required_increase_cents'] !== '0'
                || $adjustedReconciliation['summary']['calculated_amount_cents'] !== $adjustedReconciliation['summary']['abpre_amount_cents']) {
                throw ValidationException::withMessages([
                    'cuts' => 'La propuesta ajustada no concilia por actividad, partida y mes.',
                ]);
            }

            $lockedBudget->update(['status' => OwnRevenueBudgetStatus::ProposalAdjusted]);

            return $adjusted->refresh();
        }, attempts: 3);
    }

    private function lockRows(OwnRevenueProposal $proposal): void
    {
        $proposal->technicalNeeds()->lockForUpdate()->get();
        $proposal->fuelNeeds()->lockForUpdate()->get();
        $commissionIds = $proposal->travelCommissions()->lockForUpdate()->pluck('id');
        OwnRevenueProposalTravelParticipant::query()
            ->whereIn('own_revenue_proposal_travel_commission_id', $commissionIds)->lockForUpdate()->get();
    }

    /** @return array<string, array<int, Model>> */
    private function copyRows(OwnRevenueProposal $source, OwnRevenueProposal $adjusted): array
    {
        $copies = ['technical' => [], 'fuel' => [], 'travel' => []];
        foreach ($source->technicalNeeds()->get() as $need) {
            $copies['technical'][$need->id] = $this->copyProposalRow($need, $adjusted);
        }
        foreach ($source->fuelNeeds()->get() as $need) {
            $copies['fuel'][$need->id] = $this->copyProposalRow($need, $adjusted);
        }
        foreach ($source->travelCommissions()->get() as $commission) {
            /** @var OwnRevenueProposalTravelCommission $commissionCopy */
            $commissionCopy = $this->copyProposalRow($commission, $adjusted);
            $copies['travel'][$commission->id] = $commissionCopy;
            foreach ($commission->participants()->get() as $participant) {
                $participantCopy = $participant->replicate([
                    'id', 'own_revenue_proposal_travel_commission_id', 'own_revenue_proposal_id',
                    'own_revenue_budget_id', 'created_at', 'updated_at',
                ]);
                $participantCopy->own_revenue_proposal_travel_commission_id = $commissionCopy->id;
                $participantCopy->own_revenue_proposal_id = $adjusted->id;
                $participantCopy->own_revenue_budget_id = $adjusted->own_revenue_budget_id;
                $participantCopy->save();
            }
        }

        return $copies;
    }

    private function copyProposalRow(Model $source, OwnRevenueProposal $adjusted): Model
    {
        $copy = $source->replicate([
            'id', 'own_revenue_proposal_id', 'own_revenue_budget_id', 'created_at', 'updated_at',
        ]);
        $copy->own_revenue_proposal_id = $adjusted->id;
        $copy->own_revenue_budget_id = $adjusted->own_revenue_budget_id;
        $copy->save();

        return $copy;
    }

    /** @param Collection<int, OwnRevenueProposalCut> $cuts @param array<string, array<int, Model>> $copies */
    private function applyCuts(Collection $cuts, array $copies): void
    {
        foreach ($cuts as $cut) {
            $amount = BigInteger::of((string) $cut->getRawOriginal('amount_cents'));
            match ($cut->target_type) {
                'technical' => $this->reduceAttribute($copies['technical'][$cut->target_id], 'budget_amount_cents', $amount),
                'fuel' => $this->reduceAttribute($copies['fuel'][$cut->target_id], 'budget_amount_cents', $amount),
                'travel_flight' => $this->reduceAttribute($copies['travel'][$cut->target_id], 'flight_amount_cents', $amount),
                'travel_per_diem' => $this->reduceTravelParticipants($copies['travel'][$cut->target_id], 'per_diem_amount_cents', $amount),
                'travel_lodging' => $this->reduceTravelParticipants($copies['travel'][$cut->target_id], 'lodging_amount_cents', $amount),
                default => throw ValidationException::withMessages(['cuts' => 'Existe una reducción con un destino no reconocido.']),
            };
        }
    }

    /** @param list<array<string, mixed>> $groups */
    private function applyIncreases(OwnRevenueProposal $adjusted, array $groups): void
    {
        $increases = collect($groups)
            ->filter(fn (array $group): bool => $group['required_increase_cents'] !== '0')
            ->values();
        if ($increases->isEmpty()) {
            return;
        }

        $classifications = ExpenseClassification::query()
            ->where('fiscal_year', $adjusted->budget->fiscal_year)
            ->whereIn('specific_item_code', $increases->pluck('specific_item_code')->unique())
            ->get()
            ->keyBy('specific_item_code');
        $sortOrder = (int) $adjusted->technicalNeeds()->max('sort_order');

        foreach ($increases as $group) {
            $classification = $classifications->get($group['specific_item_code']);
            if ($classification === null) {
                throw ValidationException::withMessages([
                    'cuts' => "No se encontró la partida {$group['specific_item_code']} para crear el ajuste de conciliación.",
                ]);
            }
            $amount = (string) $group['required_increase_cents'];
            $adjusted->technicalNeeds()->create([
                'own_revenue_budget_id' => $adjusted->own_revenue_budget_id,
                'own_revenue_activity_id' => $group['activity_id'],
                'expense_classification_id' => $classification->id,
                'stable_key' => implode(':', [
                    'abpre-adjustment',
                    $group['activity_id'],
                    $group['specific_item_code'],
                    str_pad((string) $group['month'], 2, '0', STR_PAD_LEFT),
                ]),
                'specific_item_code' => $classification->specific_item_code,
                'specific_item_name' => $classification->specific_item_name,
                'chapter_code' => $classification->chapter_code,
                'chapter_name' => $classification->chapter_name,
                'sequence' => (string) (++$sortOrder),
                'quantity' => '1.0000',
                'unit' => 'AJUSTE',
                'description' => 'Ajuste de conciliación con ABPRE',
                'unit_price_cents' => $amount,
                'reference_amount_cents' => $amount,
                'budget_amount_cents' => $amount,
                'budget_month' => $group['month'],
                'impact_on_goals' => null,
                'region_code' => '02-001',
                'region_name' => 'Felipe Carrillo Puerto',
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function reduceAttribute(Model $model, string $attribute, BigInteger $cut): void
    {
        $current = BigInteger::of((string) $model->getRawOriginal($attribute));
        if ($cut->isGreaterThan($current)) {
            throw ValidationException::withMessages(['cuts' => 'Una reducción supera el importe disponible.']);
        }
        $model->update([$attribute => (string) $current->minus($cut)]);
    }

    private function reduceTravelParticipants(
        OwnRevenueProposalTravelCommission $commission,
        string $attribute,
        BigInteger $cut,
    ): void {
        $participants = $commission->participants()->orderBy('stable_key')->get();
        $group = [[
            'required_cut_cents' => (string) $cut,
            'candidates' => $participants->map(fn ($participant): array => [
                'stable_key' => $participant->stable_key,
                'available_amount_cents' => (string) $participant->getRawOriginal($attribute),
            ])->all(),
        ]];
        $suggestion = $this->suggestion->suggest($group);
        foreach ($participants as $participant) {
            $participantCut = BigInteger::of($suggestion[$participant->stable_key] ?? '0');
            $remaining = BigInteger::of((string) $participant->getRawOriginal($attribute))->minus($participantCut);
            $perDiem = $attribute === 'per_diem_amount_cents'
                ? $remaining
                : BigInteger::of((string) $participant->getRawOriginal('per_diem_amount_cents'));
            $lodging = $attribute === 'lodging_amount_cents'
                ? $remaining
                : BigInteger::of((string) $participant->getRawOriginal('lodging_amount_cents'));
            $participant->update([
                $attribute => (string) $remaining,
                'total_amount_cents' => (string) $perDiem->plus($lodging),
            ]);
        }
    }
}
