<?php

namespace App\Http\Requests\Finance\OwnRevenue\Execution;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Foundation\Http\FormRequest;

class RequestExpensePaymentRequest extends FormRequest
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
        return [
            'payment_request_reference' => ['required', 'string', 'max:255'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => [
                'required',
                'file',
                'max:10240',
                'extensions:pdf,xml,jpg,jpeg,png,xlsx',
                'mimetypes:application/pdf,application/xml,text/xml,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }
}
