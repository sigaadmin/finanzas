<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeactivateExpenseRequirementRule
{
    public function handle(OwnRevenueExpenseRequirementRule $rule, User $user): OwnRevenueExpenseRequirementRule
    {
        Gate::forUser($user)->authorize('manageExpenseRequirementRules', $rule->budget);

        return DB::transaction(function () use ($rule, $user): OwnRevenueExpenseRequirementRule {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($rule->own_revenue_budget_id);
            Gate::forUser($user)->authorize('manageExpenseRequirementRules', $budget);
            $lockedRule = OwnRevenueExpenseRequirementRule::query()
                ->whereBelongsTo($budget, 'budget')->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $lockedRule->update(['is_active' => false]);
            $lockedRule->dossierRequirements()
                ->where('status', OwnRevenueExpenseRequirementStatus::Pending)
                ->delete();

            return $lockedRule;
        }, attempts: 3);
    }
}
