<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\OfficialFeeLinkStatus;
use App\Models\OfficialFeeConcept;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeConceptOfficialLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->charge_concept) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'status' => ['required', Rule::enum(OfficialFeeLinkStatus::class)],
            'official_fee_concept_id' => [
                Rule::requiredIf($this->string('status')->toString() === OfficialFeeLinkStatus::Linked->value),
                'nullable',
                'integer',
                Rule::exists(OfficialFeeConcept::class, 'id'),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
