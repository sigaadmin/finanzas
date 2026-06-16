<?php

namespace App\Http\Requests\Finance;

use App\Models\Receipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->receipt;

        return $receipt instanceof Receipt
            && $this->user()?->can('cancel', $receipt);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
