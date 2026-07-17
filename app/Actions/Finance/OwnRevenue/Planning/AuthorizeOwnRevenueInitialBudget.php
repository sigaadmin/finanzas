<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuthorizeOwnRevenueInitialBudget
{
    public function __construct(private readonly OwnRevenueCutReconciliation $reconciliation) {}

    public function handle(OwnRevenueBudget $budget, OwnRevenueProposal $proposal, User $user, string $expectedFingerprint): OwnRevenueInitialBudget
    {
        Gate::forUser($user)->authorize('authorizeInitialBudget', $budget);

        return DB::transaction(function () use ($budget, $proposal, $user, $expectedFingerprint): OwnRevenueInitialBudget {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($lockedProposal->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            Gate::forUser($user)->authorize('authorizeInitialBudget', $lockedBudget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Adjusted) {
                throw new AuthorizationException;
            }
            if (OwnRevenueInitialBudget::query()->whereBelongsTo($lockedBudget, 'budget')->exists()) {
                throw ValidationException::withMessages(['proposal' => 'El presupuesto inicial ya fue autorizado.']);
            }
            OwnRevenueImportFile::query()->whereBelongsTo($lockedBudget, 'budget')->lockForUpdate()->get();
            $lockedProposal->technicalNeeds()->lockForUpdate()->get();
            $lockedProposal->fuelNeeds()->lockForUpdate()->get();
            $lockedProposal->travelCommissions()->with('participants')->lockForUpdate()->get();
            $lockedProposal->unsetRelations();
            $lockedProposal->setRelation('budget', $lockedBudget);
            $reconciliation = $this->reconciliation->forProposal($lockedProposal);
            if (! hash_equals($reconciliation['fingerprint'], $expectedFingerprint) || ! $reconciliation['ready']) {
                throw ValidationException::withMessages([
                    'authorization_fingerprint' => $reconciliation['blockers'][0] ?? 'La propuesta cambió; actualiza la página antes de continuar.',
                ]);
            }
            if ($reconciliation['summary']['pending_cut_cents'] !== '0'
                || $reconciliation['summary']['adjusted_amount_cents'] !== $reconciliation['summary']['abpre_amount_cents']) {
                throw ValidationException::withMessages(['proposal' => 'La propuesta ajustada aún no concilia con el ABPRE final.']);
            }

            $initialBudget = OwnRevenueInitialBudget::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'own_revenue_proposal_id' => $lockedProposal->id,
                'total_amount_cents' => $lockedProposal->getRawOriginal('total_amount_cents'),
                'source_fingerprint' => $lockedProposal->source_fingerprint,
                'authorization_fingerprint' => $reconciliation['fingerprint'],
                'snapshot' => $this->snapshot($lockedBudget, $lockedProposal, $reconciliation),
                'authorized_by' => $user->id,
                'authorized_at' => now(),
            ]);
            $lockedBudget->update(['status' => OwnRevenueBudgetStatus::InitialAuthorized]);

            return $initialBudget;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $reconciliation @return array<string, mixed> */
    private function snapshot(OwnRevenueBudget $budget, OwnRevenueProposal $proposal, array $reconciliation): array
    {
        return [
            'budget' => [
                'fiscal_year' => $budget->fiscal_year,
                'institution_name' => $budget->institution_name,
                'responsible_unit_code' => $budget->responsible_unit_code,
                'responsible_unit_name' => $budget->responsible_unit_name,
                'budget_program_code' => $budget->budget_program_code,
                'budget_program_name' => $budget->budget_program_name,
                'component_code' => $budget->component_code,
                'component_name' => $budget->component_name,
                'official_activity_code' => $budget->official_activity_code,
                'official_activity_name' => $budget->official_activity_name,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
                'uma_value' => $budget->uma_value,
                'fuel_price_per_liter' => $budget->fuel_price_per_liter,
            ],
            'sources' => [
                'abpre' => $proposal->source_abpre_file_id,
                'work_sheet' => $proposal->source_work_sheet_file_id,
                'technical_sheet' => $proposal->source_technical_sheet_file_id,
                'fuel' => $proposal->source_fuel_file_id,
                'travel_expenses' => $proposal->source_travel_expenses_file_id,
            ],
            'reconciliation' => $reconciliation,
            'technical_needs' => $proposal->technicalNeeds()->with('activity')->orderBy('sort_order')->get()->map(fn ($need): array => [
                'stable_key' => $need->stable_key, 'activity' => $need->activity->code,
                'activity_name' => $need->activity->name,
                'item' => $need->specific_item_code, 'item_name' => $need->specific_item_name,
                'chapter_code' => $need->chapter_code, 'chapter_name' => $need->chapter_name,
                'sequence' => $need->sequence, 'quantity' => $need->quantity, 'unit' => $need->unit,
                'description' => $need->description,
                'unit_price_cents' => (string) $need->getRawOriginal('unit_price_cents'),
                'reference_amount_cents' => (string) $need->getRawOriginal('reference_amount_cents'),
                'month' => $need->budget_month, 'impact_on_goals' => $need->impact_on_goals,
                'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
                'amount_cents' => (string) $need->getRawOriginal('budget_amount_cents'),
            ])->all(),
            'fuel_needs' => $proposal->fuelNeeds()->with('activity')->orderBy('sort_order')->get()->map(fn ($need): array => [
                'stable_key' => $need->stable_key, 'activity' => $need->activity->code,
                'activity_name' => $need->activity->name, 'item' => '26101',
                'commission_date_label' => $need->commission_date_label,
                'operational_month' => $need->operational_month, 'month' => 4,
                'reason' => $need->reason, 'vehicle_model' => $need->vehicle_model,
                'kilometers_per_liter' => $need->kilometers_per_liter,
                'outbound_origin' => $need->outbound_origin, 'outbound_destination' => $need->outbound_destination,
                'outbound_kilometers' => $need->outbound_kilometers,
                'return_origin' => $need->return_origin, 'return_destination' => $need->return_destination,
                'return_kilometers' => $need->return_kilometers, 'additional_kilometers' => $need->additional_kilometers,
                'total_kilometers' => $need->total_kilometers, 'liters' => $need->liters,
                'fuel_price' => $need->fuel_price,
                'mathematical_amount_cents' => (string) $need->getRawOriginal('mathematical_amount_cents'),
                'rounded_amount_cents' => (string) $need->getRawOriginal('rounded_amount_cents'),
                'rounding_difference_cents' => (string) $need->getRawOriginal('rounding_difference_cents'),
                'override_justification' => $need->override_justification,
                'amount_cents' => (string) $need->getRawOriginal('budget_amount_cents'),
            ])->all(),
            'travel_commissions' => $proposal->travelCommissions()->with(['activity', 'participants'])->orderBy('sort_order')->get()->map(fn ($commission): array => [
                'stable_key' => $commission->stable_key, 'activity' => $commission->activity->code,
                'activity_name' => $commission->activity->name,
                'commission_date_label' => $commission->commission_date_label,
                'operational_month' => $commission->operational_month, 'month' => $commission->budget_month,
                'reason' => $commission->reason, 'destination' => $commission->destination,
                'food_zone' => $commission->food_zone, 'lodging_zone' => $commission->lodging_zone,
                'uma_value' => $commission->uma_value,
                'flight_amount_cents' => (string) $commission->getRawOriginal('flight_amount_cents'),
                'participants_amount_cents' => (string) $commission->getRawOriginal('participants_amount_cents'),
                'total_amount_cents' => (string) $commission->getRawOriginal('total_amount_cents'),
                'override_justification' => $commission->override_justification,
                'participants' => $commission->participants->map(fn ($participant): array => [
                    'stable_key' => $participant->stable_key, 'person_name' => $participant->person_name,
                    'position' => $participant->position, 'commission_days' => $participant->commission_days,
                    'per_diem_uma' => $participant->per_diem_uma, 'lodging_uma' => $participant->lodging_uma,
                    'per_diem_amount_cents' => (string) $participant->getRawOriginal('per_diem_amount_cents'),
                    'lodging_amount_cents' => (string) $participant->getRawOriginal('lodging_amount_cents'),
                    'amount_cents' => (string) $participant->getRawOriginal('total_amount_cents'),
                ])->all(),
            ])->all(),
        ];
    }
}
