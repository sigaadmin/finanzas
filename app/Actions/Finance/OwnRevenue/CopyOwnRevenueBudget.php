<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CopyOwnRevenueBudget
{
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

    private const ACTIVITY_FIELDS = [
        'code',
        'name',
        'sort_order',
    ];

    private const SIGNATORY_FIELDS = [
        'role_key',
        'name',
        'position',
        'academic_degree',
        'sort_order',
    ];

    public function __construct(
        private InitializeOwnRevenueBudget $initializeBudget,
        private CopyExpenseClassificationsForYear $copyExpenseClassifications,
    ) {}

    public function handle(
        OwnRevenueBudget $source,
        int $destinationFiscalYear,
        User $createdBy,
    ): OwnRevenueBudget {
        return DB::transaction(function () use ($source, $destinationFiscalYear, $createdBy): OwnRevenueBudget {
            $source = $this->persistedSource($source);
            $createdBy = $this->persistedCreator($createdBy);

            $this->validateDestinationYear($source, $destinationFiscalYear);

            if (OwnRevenueBudget::query()->where('fiscal_year', $destinationFiscalYear)->exists()) {
                throw ValidationException::withMessages([
                    'fiscal_year' => 'Ya existe un presupuesto de ingresos propios para el ejercicio fiscal destino.',
                ]);
            }

            $destination = $this->initializeBudget->handle($createdBy, [
                'fiscal_year' => $destinationFiscalYear,
                ...$source->only(self::INSTITUTIONAL_FIELDS),
            ]);

            $this->copyActivities($source, $destination);
            $this->copySignatories($source, $destination);

            $destination = $this->copyExpenseClassifications->handle(
                $destination,
                $source->fiscal_year,
            );

            return $destination->load([
                'activities' => fn ($query) => $query->orderBy('sort_order'),
                'signatories' => fn ($query) => $query->orderBy('sort_order'),
            ]);
        });
    }

    private function persistedSource(OwnRevenueBudget $source): OwnRevenueBudget
    {
        if (! $source->exists || $source->getKey() === null) {
            throw ValidationException::withMessages([
                'source' => 'El presupuesto de origen debe existir.',
            ]);
        }

        $persistedSource = OwnRevenueBudget::query()
            ->whereKey($source->getKey())
            ->lockForUpdate()
            ->first();

        if ($persistedSource === null) {
            throw ValidationException::withMessages([
                'source' => 'El presupuesto de origen debe existir.',
            ]);
        }

        return $persistedSource;
    }

    private function persistedCreator(User $createdBy): User
    {
        if (! $createdBy->exists || $createdBy->getKey() === null) {
            throw ValidationException::withMessages([
                'creator' => 'El usuario creador debe existir.',
            ]);
        }

        $persistedCreator = User::query()
            ->whereKey($createdBy->getKey())
            ->lockForUpdate()
            ->first();

        if ($persistedCreator === null) {
            throw ValidationException::withMessages([
                'creator' => 'El usuario creador debe existir.',
            ]);
        }

        return $persistedCreator;
    }

    private function validateDestinationYear(OwnRevenueBudget $source, int $destinationFiscalYear): void
    {
        if ($destinationFiscalYear < 1000 || $destinationFiscalYear > 9999) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'El ejercicio fiscal destino debe contener cuatro dígitos.',
            ]);
        }

        if ($destinationFiscalYear <= $source->fiscal_year) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'El ejercicio fiscal destino debe ser posterior al ejercicio de origen.',
            ]);
        }
    }

    private function copyActivities(OwnRevenueBudget $source, OwnRevenueBudget $destination): void
    {
        $activities = $source->activities()
            ->orderBy('sort_order')
            ->get(self::ACTIVITY_FIELDS);

        if ($activities->isEmpty()) {
            $destination->activities()->delete();

            return;
        }

        $rows = $activities->map(fn (OwnRevenueActivity $activity): array => [
            'own_revenue_budget_id' => $destination->getKey(),
            ...$activity->only(self::ACTIVITY_FIELDS),
        ])->all();

        $destination->activities()->upsert(
            $rows,
            ['own_revenue_budget_id', 'code'],
            ['name', 'sort_order'],
        );

        $destination->activities()
            ->whereNotIn('code', $activities->pluck('code'))
            ->delete();
    }

    private function copySignatories(OwnRevenueBudget $source, OwnRevenueBudget $destination): void
    {
        $signatories = $source->signatories()
            ->orderBy('sort_order')
            ->get(self::SIGNATORY_FIELDS)
            ->map->only(self::SIGNATORY_FIELDS)
            ->all();

        $destination->signatories()->createMany($signatories);
    }
}
