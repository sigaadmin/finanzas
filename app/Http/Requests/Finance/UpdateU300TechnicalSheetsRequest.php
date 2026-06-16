<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateU300TechnicalSheetsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('operate-finance') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sheets' => ['required', 'array', 'min:1'],
            'sheets.*.u300_budget_line_id' => ['required', 'integer', 'exists:u300_budget_lines,id'],
            'sheets.*.item_name' => ['nullable', 'string', 'max:3000'],
            'sheets.*.objective' => ['nullable', 'string', 'max:3000'],
            'sheets.*.work_description' => ['nullable', 'string', 'max:3000'],
            'sheets.*.technical_specs' => ['nullable', 'string', 'max:5000'],
            'sheets.*.beneficiaries' => ['nullable', 'string', 'max:255'],
            'sheets.*.scheduled_date' => ['nullable', 'string', 'max:255'],
            'sheets.*.deliverables' => ['nullable', 'string', 'max:3000'],
            'sheets.*.delivery_location' => ['nullable', 'string', 'max:3000'],
            'sheets.*.supervisor' => ['nullable', 'string', 'max:1000'],
            'sheets.*.payment_terms' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function sheets(): array
    {
        return collect($this->validated('sheets'))->values()->all();
    }
}
