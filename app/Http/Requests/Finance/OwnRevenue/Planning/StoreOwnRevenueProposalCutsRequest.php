<?php

namespace App\Http\Requests\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueProposalCutsRequest extends FormRequest
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
            'cuts' => ['sometimes', 'array', 'max:10000'],
            'cuts.*.target_type' => ['required', Rule::in([
                'technical', 'fuel', 'travel_per_diem', 'travel_lodging', 'travel_flight',
            ])],
            'cuts.*.target_id' => ['required', 'integer', 'min:1'],
            'cuts.*.stable_key' => ['required', 'string', 'max:255'],
            'cuts.*.specific_item_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'cuts.*.amount_cents' => ['required', 'string', 'regex:/^(?:0|[1-9]\d*)$/'],
        ];
    }
}
