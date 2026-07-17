<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueExpenseDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('createExpenseDossier', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'own_revenue_modified_budget_line_id' => [
                'required',
                'integer',
                Rule::exists('own_revenue_modified_budget_lines', 'id')
                    ->where('own_revenue_budget_id', $this->route('budget')?->id),
            ],
            'concept' => ['required', 'string', 'max:2000'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'purchase_responsibility' => ['required', Rule::enum(OwnRevenuePurchaseResponsibility::class)],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
