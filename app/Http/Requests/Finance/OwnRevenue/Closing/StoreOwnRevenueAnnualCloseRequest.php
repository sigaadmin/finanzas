<?php

namespace App\Http\Requests\Finance\OwnRevenue\Closing;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnRevenueAnnualCloseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof OwnRevenueBudget
            && $this->user()?->can('closeAnnualBudget', $budget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $budget = $this->route('budget');
        $confirmationPhrase = $budget instanceof OwnRevenueBudget
            ? "CERRAR {$budget->fiscal_year}"
            : '';

        return [
            'confirmation' => ['required', 'string', Rule::in([$confirmationPhrase])],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Escribe exactamente la frase indicada para confirmar el cierre.',
            'note.min' => 'La nota de cierre debe tener al menos 10 caracteres.',
            'note.max' => 'La nota de cierre no puede exceder 1000 caracteres.',
        ];
    }
}
