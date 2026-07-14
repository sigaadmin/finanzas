<?php

namespace App\Http\Requests\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmOwnRevenueSupportingImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $file = $this->route('importFile');

        return $budget instanceof OwnRevenueBudget
            && $file instanceof OwnRevenueImportFile
            && $file->own_revenue_budget_id === $budget->id
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
            'analysis_revision' => ['required', 'uuid'],
        ];
    }
}
