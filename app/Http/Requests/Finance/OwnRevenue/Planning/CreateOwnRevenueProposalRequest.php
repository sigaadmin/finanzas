<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class CreateOwnRevenueProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget && $this->user()?->can('createProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'source_abpre_file_id' => ['required', 'integer', 'min:1'],
            'source_work_sheet_file_id' => ['required', 'integer', 'min:1'],
            'source_technical_sheet_file_id' => ['required', 'integer', 'min:1'],
            'source_fuel_file_id' => ['required', 'integer', 'min:1'],
            'source_travel_expenses_file_id' => ['required', 'integer', 'min:1'],
            'source_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @return array<string, int> */
    public function sourceFileIds(): array
    {
        return [
            OwnRevenueImportFormat::Abpre->value => $this->integer('source_abpre_file_id'),
            OwnRevenueImportFormat::WorkSheet->value => $this->integer('source_work_sheet_file_id'),
            OwnRevenueImportFormat::TechnicalSheet->value => $this->integer('source_technical_sheet_file_id'),
            OwnRevenueImportFormat::Fuel->value => $this->integer('source_fuel_file_id'),
            OwnRevenueImportFormat::TravelExpenses->value => $this->integer('source_travel_expenses_file_id'),
        ];
    }
}
