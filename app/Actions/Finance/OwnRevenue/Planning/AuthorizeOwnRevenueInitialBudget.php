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
                'item' => $need->specific_item_code, 'month' => $need->budget_month,
                'amount_cents' => (string) $need->getRawOriginal('budget_amount_cents'),
            ])->all(),
            'fuel_needs' => $proposal->fuelNeeds()->with('activity')->orderBy('sort_order')->get()->map(fn ($need): array => [
                'stable_key' => $need->stable_key, 'activity' => $need->activity->code,
                'item' => '26101', 'month' => $need->budget_month,
                'amount_cents' => (string) $need->getRawOriginal('budget_amount_cents'),
            ])->all(),
            'travel_commissions' => $proposal->travelCommissions()->with(['activity', 'participants'])->orderBy('sort_order')->get()->map(fn ($commission): array => [
                'stable_key' => $commission->stable_key, 'activity' => $commission->activity->code,
                'month' => $commission->budget_month,
                'flight_amount_cents' => (string) $commission->getRawOriginal('flight_amount_cents'),
                'participants_amount_cents' => (string) $commission->getRawOriginal('participants_amount_cents'),
                'participants' => $commission->participants->map(fn ($participant): array => [
                    'stable_key' => $participant->stable_key,
                    'amount_cents' => (string) $participant->getRawOriginal('total_amount_cents'),
                ])->all(),
            ])->all(),
        ];
    }
}
