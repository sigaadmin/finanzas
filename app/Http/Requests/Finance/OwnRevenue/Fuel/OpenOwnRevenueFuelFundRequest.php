<?php

namespace App\Http\Requests\Finance\OwnRevenue\Fuel;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenOwnRevenueFuelFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('openFuelFund', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        $budget = $this->route('budget');

        return [
            'source_expense_dossier_id' => [
                'required',
                'integer',
                Rule::exists('own_revenue_expense_dossiers', 'id')
                    ->where('own_revenue_budget_id', $budget instanceof OwnRevenueBudget ? $budget->id : 0),
            ],
            'acquired_amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }
}
