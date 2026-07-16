<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalTechnicalNeedRequest extends FormRequest
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
            'expense_classification_id' => ['required', 'integer', 'min:1'],
            'sequence' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,4})?$/'],
            'unit' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:4000'],
            'unit_price' => ['required', 'string', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'budget_amount_cents' => ['required', 'integer', 'min:0'],
            'budget_month' => ['required', 'integer', 'between:1,12'],
            'impact_on_goals' => ['nullable', 'string', 'max:4000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'override_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
