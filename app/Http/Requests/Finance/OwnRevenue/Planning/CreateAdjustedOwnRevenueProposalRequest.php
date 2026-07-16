<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdjustedOwnRevenueProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');
        $proposal = $this->route('proposal');

        return $budget instanceof OwnRevenueBudget
            && $proposal instanceof OwnRevenueProposal
            && $proposal->own_revenue_budget_id === $budget->id
            && $proposal->status === OwnRevenueProposalStatus::Calculated
            && $this->user()?->can('manageProposalCuts', $budget) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'reconciliation_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
