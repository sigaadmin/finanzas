<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\PaymentProcedureStatus;
use App\Models\PaymentProcedure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentProcedure = $this->payment_procedure;

        return $paymentProcedure instanceof PaymentProcedure
            && $paymentProcedure->status === PaymentProcedureStatus::PendingPayment
            && $this->user()?->can('update', $paymentProcedure);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
