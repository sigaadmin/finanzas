<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class StoreOwnRevenueTravelRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget && $this->user()?->can('editProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'position' => ['required', 'string', 'max:500'],
            'food_zone' => ['required', 'integer', 'between:1,255'],
            'lodging_zone' => ['required', 'integer', 'between:1,255'],
            'per_diem_uma' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'lodging_uma' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'is_fallback' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
