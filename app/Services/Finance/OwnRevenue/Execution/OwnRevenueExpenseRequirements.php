<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OwnRevenueExpenseRequirements
{
    public function syncAllStages(OwnRevenueExpenseDossier $dossier): void
    {
        foreach ([
            OwnRevenueExpenseDossierStatus::SufficiencyRequested,
            OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
            OwnRevenueExpenseDossierStatus::PurchaseInProgress,
            OwnRevenueExpenseDossierStatus::PaymentRequested,
            OwnRevenueExpenseDossierStatus::FinanceAuthorized,
            OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
            OwnRevenueExpenseDossierStatus::Paid,
        ] as $targetStatus) {
            if ($this->stageRank($targetStatus) <= $this->stageRank($dossier->status)) {
                continue;
            }
            $this->syncForStage($dossier, $targetStatus);
        }
    }

    /** @return Collection<int, OwnRevenueExpenseDossierRequirement> */
    public function syncForStage(
        OwnRevenueExpenseDossier $dossier,
        OwnRevenueExpenseDossierStatus $targetStatus,
    ): Collection {
        $dossier->loadMissing('budgetLine');
        $rules = OwnRevenueExpenseRequirementRule::query()
            ->where('own_revenue_budget_id', $dossier->own_revenue_budget_id)
            ->where('target_status', $targetStatus)
            ->where('is_active', true)
            ->where(function ($query) use ($dossier): void {
                $query->whereNull('purchase_responsibility')
                    ->orWhere('purchase_responsibility', $dossier->purchase_responsibility);
            })
            ->where(function ($query) use ($dossier): void {
                $query->whereNull('chapter_code')
                    ->orWhere('chapter_code', $dossier->budgetLine->chapter_code);
            })
            ->where(function ($query) use ($dossier): void {
                $query->whereNull('specific_item_code')
                    ->orWhere('specific_item_code', $dossier->budgetLine->specific_item_code);
            })
            ->where(function ($query) use ($dossier): void {
                $query->whereNull('minimum_amount_cents')
                    ->orWhere('minimum_amount_cents', '<=', $dossier->amount_cents);
            })
            ->get();

        foreach ($rules as $rule) {
            OwnRevenueExpenseDossierRequirement::query()->firstOrCreate([
                'own_revenue_expense_dossier_id' => $dossier->id,
                'own_revenue_expense_requirement_rule_id' => $rule->id,
            ]);
        }

        return OwnRevenueExpenseDossierRequirement::query()
            ->with('rule')
            ->where('own_revenue_expense_dossier_id', $dossier->id)
            ->whereIn('own_revenue_expense_requirement_rule_id', $rules->modelKeys())
            ->get();
    }

    public function assertSatisfied(
        OwnRevenueExpenseDossier $dossier,
        OwnRevenueExpenseDossierStatus $targetStatus,
    ): void {
        $requirements = OwnRevenueExpenseDossierRequirement::query()
            ->with('rule')
            ->where('own_revenue_expense_dossier_id', $dossier->id)
            ->whereHas('rule', fn ($query) => $query
                ->where('target_status', $targetStatus)
                ->where('is_active', true))
            ->lockForUpdate()
            ->get();
        $pendingTitles = $requirements
            ->where('status', OwnRevenueExpenseRequirementStatus::Pending)
            ->pluck('rule.title')
            ->values();

        if ($pendingTitles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'requirements' => 'Completa los requisitos pendientes: '.$pendingTitles->join(', ').'.',
            ]);
        }
    }

    private function stageRank(OwnRevenueExpenseDossierStatus $status): int
    {
        return match ($status) {
            OwnRevenueExpenseDossierStatus::Draft => 0,
            OwnRevenueExpenseDossierStatus::SufficiencyRequested => 1,
            OwnRevenueExpenseDossierStatus::SufficiencyConfirmed => 2,
            OwnRevenueExpenseDossierStatus::PurchaseInProgress => 3,
            OwnRevenueExpenseDossierStatus::PaymentRequested => 4,
            OwnRevenueExpenseDossierStatus::FinanceAuthorized => 5,
            OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized => 6,
            OwnRevenueExpenseDossierStatus::Paid => 7,
            OwnRevenueExpenseDossierStatus::Rejected,
            OwnRevenueExpenseDossierStatus::Cancelled => PHP_INT_MAX,
        };
    }
}
