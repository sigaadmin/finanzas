<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Models\ChargeConcept;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChargeConceptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ChargeConcept::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount_pesos' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::enum(ChargeConceptType::class)],
            'allows_quantity' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(ChargeConceptStatus::class)],
            'internal_key' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['allows_quantity'] = ($validated['type'] ?? null) === ChargeConceptType::Internal->value
            && (bool) ($validated['allows_quantity'] ?? false);

        return $validated;
    }
}
