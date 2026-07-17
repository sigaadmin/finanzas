<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class StartExpensePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('manageExpensePurchase', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return ['purchase_reference' => ['required', 'string', 'max:255']];
    }
}
