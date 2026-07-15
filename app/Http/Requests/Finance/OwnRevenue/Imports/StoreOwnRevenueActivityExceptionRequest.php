<?php

namespace App\Http\Requests\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueActivityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('confirmImports', $budget) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'format' => [
                'required',
                Rule::enum(OwnRevenueImportFormat::class)->only([
                    OwnRevenueImportFormat::TechnicalSheet,
                    OwnRevenueImportFormat::Fuel,
                    OwnRevenueImportFormat::TravelExpenses,
                ]),
            ],
            'activity_id' => ['required', 'integer'],
            'justification' => ['required', Rule::enum(OwnRevenueActivityJustification::class)],
            'justification_note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->input('justification') === OwnRevenueActivityJustification::Other->value),
            ],
            'expected_work_sheet_file_id' => ['required', 'integer'],
            'expected_supporting_file_id' => ['required', 'integer'],
        ];
    }
}
