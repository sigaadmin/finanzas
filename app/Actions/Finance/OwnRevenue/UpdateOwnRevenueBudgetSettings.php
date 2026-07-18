<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Actions\Finance\OwnRevenue\Planning\CreateOwnRevenueProposalRevision;
use App\Data\Finance\OwnRevenue\UnsignedBigInteger;
use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateOwnRevenueBudgetSettings
{
    public function __construct(private readonly CreateOwnRevenueProposalRevision $proposalRevision) {}

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

    private const SIGNATORY_FIELDS = [
        'role_key',
        'name',
        'position',
        'academic_degree',
        'sort_order',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(OwnRevenueBudget $budget, array $data, ?User $user = null): OwnRevenueBudget
    {
        return DB::transaction(function () use ($budget, $data, $user): OwnRevenueBudget {
            $budget = OwnRevenueBudget::query()
                ->whereKey($budget->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($budget->status, [
                OwnRevenueBudgetStatus::Draft,
                OwnRevenueBudgetStatus::ProposalCalculated,
                OwnRevenueBudgetStatus::ProposalAdjusted,
            ], true)) {
                throw ValidationException::withMessages([
                    'budget' => 'La fotografía institucional ya no puede modificarse después de autorizar el presupuesto inicial.',
                ]);
            }

            $settings = $this->validatedSettings($budget, $data);
            $requiresRevision = $budget->status !== OwnRevenueBudgetStatus::Draft
                && $this->institutionalSettingsChanged($budget, $settings);
            if ($budget->status !== OwnRevenueBudgetStatus::Draft) {
                $settings = Arr::only($settings, self::INSTITUTIONAL_FIELDS);
            }

            $budget->update([
                ...$settings,
                'region_code' => self::REGION_CODE,
                'region_name' => self::REGION_NAME,
                'fuel_budget_month' => self::FUEL_BUDGET_MONTH,
            ]);

            if ($budget->status === OwnRevenueBudgetStatus::Draft && array_key_exists('signatories', $data)) {
                $this->replaceSignatories($budget, $data['signatories']);
            }

            if ($requiresRevision) {
                if ($user === null) {
                    throw ValidationException::withMessages([
                        'budget' => 'Se requiere una persona responsable para crear la nueva versión de la propuesta.',
                    ]);
                }
                $source = OwnRevenueProposal::query()
                    ->whereBelongsTo($budget, 'budget')
                    ->whereIn('status', [OwnRevenueProposalStatus::Calculated, OwnRevenueProposalStatus::Adjusted])
                    ->orderByDesc('version_number')
                    ->first();
                if ($source === null) {
                    throw ValidationException::withMessages([
                        'budget' => 'No se encontró una propuesta calculada para crear la nueva versión.',
                    ]);
                }
                $this->proposalRevision->handle($budget, $source, $user);
            }

            return $budget->refresh()->load([
                'signatories' => fn ($query) => $query->orderBy('sort_order'),
            ]);
        });
    }

    /** @param array<string, mixed> $settings */
    private function institutionalSettingsChanged(OwnRevenueBudget $budget, array $settings): bool
    {
        foreach (Arr::only($settings, self::INSTITUTIONAL_FIELDS) as $field => $value) {
            if ($budget->{$field} !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $signatories
     */
    private function replaceSignatories(OwnRevenueBudget $budget, array $signatories): void
    {
        $budget->signatories()->delete();
        $budget->signatories()->createMany(
            collect($signatories)
                ->map(fn (array $signatory): array => Arr::only($signatory, self::SIGNATORY_FIELDS))
                ->all(),
        );
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

        if (array_key_exists('estimated_income_cents', $settings)) {
            $settings['estimated_income_cents'] = UnsignedBigInteger::normalize(
                $settings['estimated_income_cents'],
            );
        }

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
                    if (! UnsignedBigInteger::isValid($value)) {
                        $fail("El campo {$attribute} debe ser un entero no negativo válido.");
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
