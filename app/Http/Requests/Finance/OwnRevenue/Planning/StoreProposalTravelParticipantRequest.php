<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalTravelParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $proposal = $this->route('proposal');
        $commission = $this->route('travelCommission');

        return $budget instanceof OwnRevenueBudget && $proposal instanceof OwnRevenueProposal
            && $commission instanceof OwnRevenueProposalTravelCommission
            && $proposal->own_revenue_budget_id === $budget->id
            && $commission->own_revenue_proposal_id === $proposal->id
            && $proposal->status === OwnRevenueProposalStatus::Draft
            && $this->user()?->can('editProposal', $budget) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'person_name' => ['required', 'string', 'max:500'],
            'position' => ['required', 'string', 'max:500'],
            'commission_days' => ['required', 'string', 'regex:/^\d{1,4}(?:\.\d{1,4})?$/', 'not_in:0,0.0,0.00,0.000,0.0000'],
            'per_diem_uma' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'lodging_uma' => ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'override_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
