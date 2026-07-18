<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateExpenseRequirementRule
{
    public function __construct(private readonly OwnRevenueExpenseRequirements $requirements) {}

    /**
     * @param  array{title: string, description?: ?string, target_status: string, purchase_responsibility?: ?string, chapter_code?: ?string, specific_item_code?: ?string, minimum_amount_cents?: ?int, requires_evidence?: bool}  $data
     */
    public function handle(OwnRevenueBudget $budget, User $user, array $data): OwnRevenueExpenseRequirementRule
    {
        Gate::forUser($user)->authorize('manageExpenseRequirementRules', $budget);

        return DB::transaction(function () use ($budget, $user, $data): OwnRevenueExpenseRequirementRule {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('manageExpenseRequirementRules', $lockedBudget);
            $rule = OwnRevenueExpenseRequirementRule::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'title' => trim($data['title']),
                'description' => $this->nullableTrimmed($data['description'] ?? null),
                'target_status' => OwnRevenueExpenseDossierStatus::from($data['target_status']),
                'purchase_responsibility' => isset($data['purchase_responsibility'])
                    ? OwnRevenuePurchaseResponsibility::from($data['purchase_responsibility'])
                    : null,
                'chapter_code' => $this->nullableTrimmed($data['chapter_code'] ?? null),
                'specific_item_code' => $this->nullableTrimmed($data['specific_item_code'] ?? null),
                'minimum_amount_cents' => $data['minimum_amount_cents'] ?? null,
                'requires_evidence' => $data['requires_evidence'] ?? false,
                'is_active' => true,
                'created_by' => $user->id,
            ]);
            OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereNotIn('status', [
                    OwnRevenueExpenseDossierStatus::Paid,
                    OwnRevenueExpenseDossierStatus::Rejected,
                    OwnRevenueExpenseDossierStatus::Cancelled,
                ])
                ->lockForUpdate()
                ->each(fn (OwnRevenueExpenseDossier $dossier) => $this->requirements->syncAllStages($dossier));

            return $rule;
        }, attempts: 3);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
