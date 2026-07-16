<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\TechnicalNeedCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProposalTechnicalNeed
{
    public function __construct(
        private readonly TechnicalNeedCalculator $calculator,
        private readonly FixedDecimal $decimal,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        OwnRevenueProposal $proposal,
        User $user,
        array $data,
        ?OwnRevenueProposalTechnicalNeed $need = null,
    ): OwnRevenueProposalTechnicalNeed {
        Gate::forUser($user)->authorize('editProposal', $proposal->budget);

        return DB::transaction(function () use ($proposal, $user, $data, $need): OwnRevenueProposalTechnicalNeed {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            Gate::forUser($user)->authorize('editProposal', $lockedProposal->budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Draft) {
                throw new AuthorizationException;
            }
            $lockedNeed = $need === null
                ? null
                : OwnRevenueProposalTechnicalNeed::query()->lockForUpdate()->findOrFail($need->id);
            if ($lockedProposal->own_revenue_budget_id !== $proposal->own_revenue_budget_id
                || ($lockedNeed !== null && $lockedNeed->own_revenue_proposal_id !== $lockedProposal->id)) {
                abort(404);
            }

            $activity = OwnRevenueActivity::query()
                ->whereBelongsTo($lockedProposal->budget, 'budget')
                ->find($data['own_revenue_activity_id']);
            if ($activity === null) {
                throw ValidationException::withMessages([
                    'own_revenue_activity_id' => 'Selecciona una actividad de este presupuesto.',
                ]);
            }
            $classification = ExpenseClassification::query()
                ->where('fiscal_year', $lockedProposal->budget->fiscal_year)
                ->find($data['expense_classification_id']);
            if ($classification === null) {
                throw ValidationException::withMessages([
                    'expense_classification_id' => 'Selecciona una partida del ejercicio del presupuesto.',
                ]);
            }

            $referenceCents = $this->calculator->referenceCents($data['quantity'], $data['unit_price']);
            $budgetAmountCents = (string) $data['budget_amount_cents'];
            $justification = trim((string) ($data['override_justification'] ?? ''));
            if ($budgetAmountCents !== $referenceCents && $justification === '') {
                throw ValidationException::withMessages([
                    'override_justification' => 'Explica por qué el importe definitivo difiere del cálculo de referencia.',
                ]);
            }

            $attributes = [
                'own_revenue_budget_id' => $lockedProposal->own_revenue_budget_id,
                'own_revenue_activity_id' => $activity->id,
                'expense_classification_id' => $classification->id,
                'specific_item_code' => $classification->specific_item_code,
                'specific_item_name' => $classification->specific_item_name,
                'chapter_code' => $classification->chapter_code,
                'chapter_name' => $classification->chapter_name,
                'sequence' => $data['sequence'] ?? null,
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'description' => $data['description'],
                'unit_price_cents' => $this->decimal->centsHalfUp($data['unit_price']),
                'reference_amount_cents' => $referenceCents,
                'budget_amount_cents' => $budgetAmountCents,
                'budget_month' => $data['budget_month'],
                'impact_on_goals' => $data['impact_on_goals'] ?? null,
                'region_code' => '02-001',
                'region_name' => 'Felipe Carrillo Puerto',
                'sort_order' => $data['sort_order'] ?? 0,
            ];

            if ($lockedNeed === null) {
                $lockedNeed = $lockedProposal->technicalNeeds()->create([
                    ...$attributes,
                    'stable_key' => 'manual:'.Str::uuid(),
                ]);
            } else {
                $lockedNeed->update($attributes);
            }

            if ($budgetAmountCents !== $referenceCents) {
                $lockedNeed->corrections()->create([
                    'own_revenue_proposal_id' => $lockedProposal->id,
                    'field' => 'budget_amount_cents',
                    'old_value' => $referenceCents,
                    'new_value' => $budgetAmountCents,
                    'justification' => $justification,
                    'corrected_by' => $user->id,
                    'corrected_at' => now(),
                ]);
            }

            $this->recalculateProposalTotal($lockedProposal);

            return $lockedNeed->refresh();
        }, attempts: 3);
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
