<?php

namespace App\Http\Requests\Finance;

use App\Models\Receipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSeqDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->receipt;

        return $receipt instanceof Receipt
            && $this->user()?->can('registerSeqDeposit', $receipt);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'deposit_date' => ['required', 'date'],
            'bank_transaction_folio' => ['required', 'string', 'max:255'],
            'deposit_type' => ['required', 'string', 'max:255'],
            'deposit_concept' => ['required', 'string', 'max:255'],
            'amount_pesos' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $receipt = $this->receipt;

                if (! $receipt instanceof Receipt) {
                    return;
                }

                if ((int) $this->input('amount_pesos') !== $receipt->total_pesos) {
                    $validator->errors()->add(
                        'amount_pesos',
                        'El importe del depósito debe coincidir exactamente con el recibo externo.'
                    );
                }
            },
        ];
    }
}
