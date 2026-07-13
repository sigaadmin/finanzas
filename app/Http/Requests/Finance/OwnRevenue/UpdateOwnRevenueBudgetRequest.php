<?php

namespace App\Http\Requests\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnRevenueBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('updateSettings', $budget) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['sometimes', 'required', 'string', 'max:255'],
            'responsible_unit_code' => ['sometimes', 'required', 'string', 'max:50'],
            'responsible_unit_name' => ['sometimes', 'required', 'string', 'max:255'],
            'budget_program_code' => ['sometimes', 'required', 'string', 'max:50'],
            'budget_program_name' => ['sometimes', 'required', 'string', 'max:255'],
            'component_code' => ['sometimes', 'required', 'string', 'max:50'],
            'component_name' => ['sometimes', 'required', 'string', 'max:255'],
            'official_activity_code' => ['sometimes', 'required', 'string', 'max:50'],
            'official_activity_name' => ['sometimes', 'required', 'string', 'max:255'],
            'estimated_income_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cut_percentage' => ['sometimes', 'nullable', 'string', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
            'uma_value' => ['sometimes', 'nullable', 'string', 'decimal:0,4', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'uma_status' => ['sometimes', 'nullable', Rule::enum(AnnualValueStatus::class)],
            'fuel_price_per_liter' => ['sometimes', 'nullable', 'string', 'decimal:0,4', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'fuel_price_status' => ['sometimes', 'nullable', Rule::enum(AnnualValueStatus::class)],
            'signatories' => ['sometimes', 'array', 'max:10'],
            'signatories.*.role_key' => ['required', 'string', 'max:100', 'distinct:strict'],
            'signatories.*.name' => ['required', 'string', 'max:255'],
            'signatories.*.position' => ['required', 'string', 'max:255'],
            'signatories.*.academic_degree' => ['nullable', 'string', 'max:100'],
            'signatories.*.sort_order' => ['required', 'integer', 'min:1'],
        ];
    }
}
