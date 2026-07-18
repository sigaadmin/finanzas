<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('cancelExpenseDossier', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
