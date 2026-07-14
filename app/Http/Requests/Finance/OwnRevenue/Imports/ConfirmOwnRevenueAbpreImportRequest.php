<?php

namespace App\Http\Requests\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmOwnRevenueAbpreImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('confirmImports', $budget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['present', 'array'],
            'decisions.*' => ['required', 'array:issue_id,resolution,resolved_value,justification'],
            'decisions.*.issue_id' => ['required', 'integer'],
            'decisions.*.resolution' => ['required', Rule::in(['manual', 'xlsx', 'custom'])],
            'decisions.*.resolved_value' => ['present', 'nullable'],
            'decisions.*.justification' => ['nullable', 'string'],
        ];
    }
}
