<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalFuelNeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $proposal = $this->route('proposal');

        return $budget instanceof OwnRevenueBudget
            && $proposal instanceof OwnRevenueProposal
            && $proposal->own_revenue_budget_id === $budget->id
            && $proposal->status === OwnRevenueProposalStatus::Draft
            && $this->user()?->can('editProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'own_revenue_activity_id' => ['required', 'integer', 'min:1'],
            'own_revenue_route_id' => ['required', 'integer', 'min:1'],
            'commission_date_label' => ['nullable', 'string', 'max:100'],
            'operational_month' => ['required', 'integer', 'between:1,12'],
            'reason' => ['required', 'string', 'max:4000'],
            'vehicle_model' => ['required', 'string', 'max:500'],
            'kilometers_per_liter' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/', 'not_in:0,0.0,0.00,0.000,0.0000'],
            'outbound_origin' => ['nullable', 'string', 'max:500'],
            'outbound_destination' => ['nullable', 'string', 'max:500'],
            'return_origin' => ['nullable', 'string', 'max:500'],
            'return_destination' => ['nullable', 'string', 'max:500'],
            'outbound_kilometers' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'return_kilometers' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'additional_kilometers' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'fuel_price' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'budget_amount_cents' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'override_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
