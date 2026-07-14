<?php

namespace App\Http\Requests\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueImportDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $file = $this->route('importFile');
        $issue = $this->route('issue');

        return $budget instanceof OwnRevenueBudget
            && $file instanceof OwnRevenueImportFile
            && $issue instanceof OwnRevenueImportIssue
            && $file->own_revenue_budget_id === $budget->id
            && $issue->own_revenue_import_file_id === $file->id
            && $this->user()?->can('confirmImports', $budget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'analysis_revision' => ['required', 'string'],
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
