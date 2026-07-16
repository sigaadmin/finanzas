<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class StoreOwnRevenueRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('editProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'max:500'],
            'destination' => ['required', 'string', 'max:500'],
            'one_way_kilometers' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'additional_kilometers' => ['sometimes', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
