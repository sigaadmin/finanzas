<?php

namespace App\Http\Requests\Finance;

use App\Enums\Finance\OfficialFeeScheduleStatus;
use App\Models\ChargeConcept;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfficialFeeConceptRequest extends FormRequest
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
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'source_name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'published_on' => ['nullable', 'date'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'amount_pesos' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'schedule_status' => ['nullable', 'in:'.implode(',', array_map(fn (OfficialFeeScheduleStatus $status): string => $status->value, OfficialFeeScheduleStatus::cases()))],
        ];
    }
}
