<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalTravelCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $proposal = $this->route('proposal');

        return $budget instanceof OwnRevenueBudget && $proposal instanceof OwnRevenueProposal
            && $proposal->own_revenue_budget_id === $budget->id
            && $proposal->status === OwnRevenueProposalStatus::Draft
            && $this->user()?->can('editProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'own_revenue_activity_id' => ['required', 'integer', 'min:1'],
            'own_revenue_travel_destination_id' => ['required', 'integer', 'min:1'],
            'commission_date_label' => ['nullable', 'string', 'max:100'],
            'operational_month' => ['required', 'integer', 'between:1,12'],
            'budget_month' => ['required', 'integer', 'between:1,12'],
            'reason' => ['required', 'string', 'max:4000'],
            'food_zone' => ['nullable', 'integer', 'between:1,255'],
            'lodging_zone' => ['nullable', 'integer', 'between:1,255'],
            'uma_value' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'flight_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'override_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
