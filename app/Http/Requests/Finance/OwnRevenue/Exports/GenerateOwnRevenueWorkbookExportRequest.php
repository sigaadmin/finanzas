<?php

namespace App\Http\Requests\Finance\OwnRevenue\Exports;

use App\Actions\Finance\OwnRevenue\Exports\GenerateOwnRevenueWorkbookExport;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateOwnRevenueWorkbookExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $initialBudget = $this->route('initialBudget');

        return $budget instanceof OwnRevenueBudget
            && $initialBudget instanceof OwnRevenueInitialBudget
            && $initialBudget->own_revenue_budget_id === $budget->id
            && $this->user()?->can('generateWorkbookExports', $budget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(GenerateOwnRevenueWorkbookExport::FORMATS)],
        ];
    }
}
