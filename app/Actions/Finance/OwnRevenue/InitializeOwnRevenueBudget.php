<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InitializeOwnRevenueBudget
{
    private const REGION_CODE = '02-001';

    private const REGION_NAME = 'Felipe Carrillo Puerto';

    private const FUEL_BUDGET_MONTH = 4;

    private const FISCAL_YEAR_UNIQUE_INDEX = 'own_revenue_budgets_fiscal_year_unique';

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

    private const ACTIVITIES = [
        ['code' => 'A01', 'name' => 'Fomento de la investigación', 'sort_order' => 1],
        ['code' => 'A02', 'name' => 'Profesorado y docencia', 'sort_order' => 2],
        ['code' => 'A03', 'name' => 'Difusión', 'sort_order' => 3],
        ['code' => 'A04', 'name' => 'Gestión', 'sort_order' => 4],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $createdBy, array $data): OwnRevenueBudget
    {
        try {
            return DB::transaction(function () use ($createdBy, $data): OwnRevenueBudget {
                $settings = $this->validatedSettings($data);

                $budget = OwnRevenueBudget::query()->create([
                    ...$settings,
                    'created_by' => $createdBy->getKey(),
                    'status' => OwnRevenueBudgetStatus::Draft,
                    'region_code' => self::REGION_CODE,
                    'region_name' => self::REGION_NAME,
                    'fuel_budget_month' => self::FUEL_BUDGET_MONTH,
                    'cog_status' => CogCatalogStatus::PendingConfirmation,
                ]);

                $budget->activities()->createMany(self::ACTIVITIES);

                return $budget->load(['activities' => fn ($query) => $query->orderBy('sort_order')]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isFiscalYearConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'fiscal_year' => 'Ya existe un presupuesto de ingresos propios para este ejercicio fiscal.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedSettings(array $data): array
    {
        $settings = Arr::only($data, [
            'fiscal_year',
            ...self::INSTITUTIONAL_FIELDS,
            ...self::OPTIONAL_SETTING_FIELDS,
        ]);

        $settings['uma_status'] = $this->annualValueStatus(
            $settings['uma_value'] ?? null,
            $settings['uma_status'] ?? null,
        );
        $settings['fuel_price_status'] = $this->annualValueStatus(
            $settings['fuel_price_per_liter'] ?? null,
            $settings['fuel_price_status'] ?? null,
        );

        $institutionalRules = collect(self::INSTITUTIONAL_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => ['required', 'string', 'max:255']])
            ->all();

        return Validator::make($settings, [
            'fiscal_year' => [
                'required',
                'integer',
                'between:2000,9999',
            ],
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
            'uma_status' => $this->annualStatusRules($settings['uma_value'] ?? null),
            'fuel_price_per_liter' => ['sometimes', 'nullable', 'string', 'regex:/^(?=.*[1-9])\d{1,8}(?:\.\d{1,4})?$/'],
            'fuel_price_status' => $this->annualStatusRules($settings['fuel_price_per_liter'] ?? null),
        ])->validate();
    }

    private function annualValueStatus(mixed $value, mixed $status): mixed
    {
        if ($status instanceof AnnualValueStatus) {
            return $status->value;
        }

        if ($status !== null) {
            return $status;
        }

        return $value === null
            ? AnnualValueStatus::PendingReview->value
            : AnnualValueStatus::Provisional->value;
    }

    private function isFiscalYearConflict(UniqueConstraintViolationException $exception): bool
    {
        return $exception->columns === ['fiscal_year']
            || $exception->index === self::FISCAL_YEAR_UNIQUE_INDEX;
    }

    /**
     * @return array<int, mixed>
     */
    private function annualStatusRules(mixed $value): array
    {
        $allowedStatuses = $value === null
            ? [AnnualValueStatus::PendingReview->value]
            : [AnnualValueStatus::Provisional->value, AnnualValueStatus::Final->value];

        return ['required', Rule::in($allowedStatuses)];
    }
}
