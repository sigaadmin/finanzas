<?php

namespace App\Http\Requests\Finance\OwnRevenue;

use App\Data\Finance\OwnRevenue\UnsignedBigInteger;
use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOwnRevenueBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OwnRevenueBudget::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $copyMode = $this->filled('source_budget_id');
        $institutionalRule = $copyMode ? 'prohibited' : 'required';
        $annualRule = $copyMode ? 'prohibited' : 'sometimes';

        return [
            'source_budget_id' => ['nullable', 'integer', Rule::exists(OwnRevenueBudget::class, 'id')],
            'fiscal_year' => [
                'required',
                'integer',
                'digits:4',
                'between:2000,9999',
                Rule::unique(OwnRevenueBudget::class, 'fiscal_year'),
            ],
            'institution_name' => [$institutionalRule, 'string', 'max:255'],
            'responsible_unit_code' => [$institutionalRule, 'string', 'max:50'],
            'responsible_unit_name' => [$institutionalRule, 'string', 'max:255'],
            'budget_program_code' => [$institutionalRule, 'string', 'max:50'],
            'budget_program_name' => [$institutionalRule, 'string', 'max:255'],
            'component_code' => [$institutionalRule, 'string', 'max:50'],
            'component_name' => [$institutionalRule, 'string', 'max:255'],
            'official_activity_code' => [$institutionalRule, 'string', 'max:50'],
            'official_activity_name' => [$institutionalRule, 'string', 'max:255'],
            'estimated_income_cents' => [
                $annualRule,
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! UnsignedBigInteger::isValid($value)) {
                        $fail("El campo {$attribute} debe ser un entero no negativo válido.");
                    }
                },
            ],
            'cut_percentage' => [$annualRule, 'nullable', 'string', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
            'uma_value' => [$annualRule, 'nullable', 'string', 'decimal:0,4', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'uma_status' => [$annualRule, 'nullable', Rule::enum(AnnualValueStatus::class)],
            'fuel_price_per_liter' => [$annualRule, 'nullable', 'string', 'decimal:0,4', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'fuel_price_status' => [$annualRule, 'nullable', Rule::enum(AnnualValueStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('estimated_income_cents')) {
            $this->merge([
                'estimated_income_cents' => UnsignedBigInteger::normalize(
                    $this->input('estimated_income_cents'),
                ),
            ]);
        }
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('source_budget_id')
                    || $validator->errors()->has('fiscal_year')
                    || ! $this->filled('source_budget_id')) {
                    return;
                }

                $sourceYear = OwnRevenueBudget::query()
                    ->whereKey($this->integer('source_budget_id'))
                    ->value('fiscal_year');

                if ($sourceYear !== null && $sourceYear >= $this->integer('fiscal_year')) {
                    $validator->errors()->add(
                        'source_budget_id',
                        'El presupuesto de origen debe pertenecer a un ejercicio anterior.',
                    );
                }
            },
        ];
    }
}
