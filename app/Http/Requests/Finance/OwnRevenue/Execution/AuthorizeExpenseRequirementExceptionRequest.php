<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class AuthorizeExpenseRequirementExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('exceptExpenseRequirement', $budget) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'exception_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,application/xml,text/xml,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extensions:pdf,xml,jpg,jpeg,png,xlsx'],
        ];
    }
}
