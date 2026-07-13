<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateOwnRevenueBudgetSettings
{
    private const REGION_CODE = '02-001';

    private const REGION_NAME = 'Felipe Carrillo Puerto';

    private const FUEL_BUDGET_MONTH = 4;

    private const INSTITUTIONAL_FIELDS = [
        'institution_name',
        'responsible_unit_code',
        'responsible_unit_name',
        'budget_program_code',
        'budget_program_name',
        'component_code',
        'component_name',
        'official_activity_code',
        'official_activity_name',
    ];

    private const OPTIONAL_SETTING_FIELDS = [
        'estimated_income_cents',
        'cut_percentage',
        'uma_value',
        'uma_status',
        'fuel_price_per_liter',
        'fuel_price_status',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(OwnRevenueBudget $budget, array $data): OwnRevenueBudget
    {
        return DB::transaction(function () use ($budget, $data): OwnRevenueBudget {
            $budget = OwnRevenueBudget::query()
                ->whereKey($budget->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($budget->status !== OwnRevenueBudgetStatus::Draft) {
                throw ValidationException::withMessages([
                    'budget' => 'Sólo se puede modificar la configuración de un presupuesto en borrador.',
                ]);
            }

            $settings = $this->validatedSettings($budget, $data);

            $budget->update([
                ...$settings,
                'region_code' => self::REGION_CODE,
                'region_name' => self::REGION_NAME,
                'fuel_budget_month' => self::FUEL_BUDGET_MONTH,
            ]);

            return $budget->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedSettings(OwnRevenueBudget $budget, array $data): array
    {
        $settings = Arr::only($data, [
            ...self::INSTITUTIONAL_FIELDS,
            ...self::OPTIONAL_SETTING_FIELDS,
        ]);

        $settings = $this->prepareAnnualValuePair(
            $settings,
            $budget,
            'uma_value',
            'uma_status',
        );
        $settings = $this->prepareAnnualValuePair(
            $settings,
            $budget,
            'fuel_price_per_liter',
            'fuel_price_status',
        );

        $institutionalRules = collect(self::INSTITUTIONAL_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => ['sometimes', 'required', 'string', 'max:255']])
            ->all();

        $validated = Validator::make($settings, [
            ...$institutionalRules,
            'estimated_income_cents' => [
                'sometimes',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_int($value) || $value < 0) {
                        $fail("El campo {$attribute} debe ser un entero mayor o igual a cero.");
                    }
                },
            ],
            'cut_percentage' => ['sometimes', 'nullable', 'string', 'regex:/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/'],
            'uma_value' => ['sometimes', 'nullable', 'string', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'uma_status' => $this->annualStatusRules($settings, $budget, 'uma_value'),
            'fuel_price_per_liter' => ['sometimes', 'nullable', 'string', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'fuel_price_status' => $this->annualStatusRules($settings, $budget, 'fuel_price_per_liter'),
        ])->validate();

        return Arr::only($validated, array_keys($settings));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function prepareAnnualValuePair(
        array $settings,
        OwnRevenueBudget $budget,
        string $valueField,
        string $statusField,
    ): array {
        if (! array_key_exists($valueField, $settings) && ! array_key_exists($statusField, $settings)) {
            return $settings;
        }

        $valueWasProvided = array_key_exists($valueField, $settings);
        $value = $valueWasProvided ? $settings[$valueField] : $budget->{$valueField};

        if (($settings[$statusField] ?? null) instanceof AnnualValueStatus) {
            $settings[$statusField] = $settings[$statusField]->value;
        }

        if (! array_key_exists($statusField, $settings)) {
            $settings[$statusField] = $value === null
                ? AnnualValueStatus::PendingReview->value
                : ($valueWasProvided ? AnnualValueStatus::Provisional->value : $budget->{$statusField}->value);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, mixed>
     */
    private function annualStatusRules(
        array $settings,
        OwnRevenueBudget $budget,
        string $valueField,
    ): array {
        $value = array_key_exists($valueField, $settings)
            ? $settings[$valueField]
            : $budget->{$valueField};
        $allowedStatuses = $value === null
            ? [AnnualValueStatus::PendingReview->value]
            : [AnnualValueStatus::Provisional->value, AnnualValueStatus::Final->value];

        return ['sometimes', 'required', Rule::in($allowedStatuses)];
    }
}
