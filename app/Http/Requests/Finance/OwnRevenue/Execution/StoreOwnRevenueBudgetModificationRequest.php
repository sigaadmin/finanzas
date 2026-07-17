<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueBudgetModificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('manageExecution', $budget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OwnRevenueBudgetModificationType::class)],
            'source_line_id' => [
                'required',
                'integer',
                Rule::exists('own_revenue_modified_budget_lines', 'id')
                    ->where('own_revenue_budget_id', $this->route('budget')?->id),
            ],
            'destination_expense_classification_id' => ['required', 'integer', 'exists:expense_classifications,id'],
            'destination_month' => ['required', 'integer', 'between:1,12'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
