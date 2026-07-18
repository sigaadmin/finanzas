<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequirementRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('manageExpenseRequirementRules', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_status' => ['required', Rule::in([
                OwnRevenueExpenseDossierStatus::SufficiencyRequested->value,
                OwnRevenueExpenseDossierStatus::SufficiencyConfirmed->value,
                OwnRevenueExpenseDossierStatus::PurchaseInProgress->value,
                OwnRevenueExpenseDossierStatus::PaymentRequested->value,
                OwnRevenueExpenseDossierStatus::FinanceAuthorized->value,
                OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized->value,
                OwnRevenueExpenseDossierStatus::Paid->value,
            ])],
            'purchase_responsibility' => ['nullable', Rule::enum(OwnRevenuePurchaseResponsibility::class)],
            'chapter_code' => ['nullable', 'string', 'max:10'],
            'specific_item_code' => ['nullable', 'string', 'max:20'],
            'minimum_amount_cents' => ['nullable', 'integer', 'min:1'],
            'requires_evidence' => ['required', 'boolean'],
        ];
    }
}
