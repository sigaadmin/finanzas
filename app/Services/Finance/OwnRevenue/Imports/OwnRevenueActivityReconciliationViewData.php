<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OverflowException;

class OwnRevenueActivityReconciliationViewData
{
    /** @var list<OwnRevenueImportFormat> */
    private const SUPPORTING_FORMATS = [
        OwnRevenueImportFormat::TechnicalSheet,
        OwnRevenueImportFormat::Fuel,
        OwnRevenueImportFormat::TravelExpenses,
    ];

    public function __construct(
        private readonly OwnRevenueActivityGroupKey $groupKeys,
        private readonly PortableIntegerAmount $amounts,
    ) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $activities = $budget->activities()->orderBy('sort_order')->orderBy('code')->get();
        $files = $this->currentConfirmedFiles($budget);
        $workSheet = $files->get(OwnRevenueImportFormat::WorkSheet->value);
        $workSheetLines = $workSheet?->workSheetLines ?? new EloquentCollection;
        $rules = $budget->activityRules()
            ->with('activity:id,own_revenue_budget_id,code,name,sort_order')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (OwnRevenueActivityRule $rule): string => $rule->format->value.'|'.$rule->group_hash);

        $formats = [];
        foreach (self::SUPPORTING_FORMATS as $format) {
            $file = $files->get($format->value);
            $formats[$format->value] = $this->formatData(
                $format,
                $file,
                $workSheetLines,
                $activities,
                $rules,
                $budget,
            );
        }

        $summary = $this->summary(collect($formats)->sum('summary.total'), collect($formats)->sum('summary.assigned'));
        $supportingFileIds = collect(self::SUPPORTING_FORMATS)
            ->mapWithKeys(fn (OwnRevenueImportFormat $format): array => [
                $format->value => $files->get($format->value)?->id,
            ])
            ->filter(fn (?int $id): bool => $id !== null)
            ->all();
        $emptyReasons = [];
        if ($workSheet === null) {
            $emptyReasons['work_sheet'] = 'Confirma una Hoja de trabajo antes de conciliar actividades.';
        }
        if ($supportingFileIds === []) {
            $emptyReasons['supporting'] = 'No hay archivos complementarios confirmados para conciliar.';
        }

        return [
            'summary' => $summary,
            'snapshots' => [
                'work_sheet_file_id' => $workSheet?->id,
                'supporting_file_ids' => $supportingFileIds,
            ],
            'activities' => $activities->map(fn (OwnRevenueActivity $activity): array => $this->activityData($activity))->values()->all(),
            'formats' => $formats,
            'empty_reasons' => $emptyReasons,
        ];
    }

    /** @return Collection<string, OwnRevenueImportFile> */
    private function currentConfirmedFiles(OwnRevenueBudget $budget): Collection
    {
        $formats = [OwnRevenueImportFormat::WorkSheet, ...self::SUPPORTING_FORMATS];
        $currentIds = $budget->importFiles()
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->whereIn('format', $formats)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->get(['id', 'format', 'version_number'])
            ->unique(fn (OwnRevenueImportFile $file): string => $file->format->value)
            ->pluck('id');

        if ($currentIds->isEmpty()) {
            return collect();
        }

        return OwnRevenueImportFile::query()
            ->whereIn('id', $currentIds)
            ->with([
                'workSheetLines.activity:id,own_revenue_budget_id,code,name,sort_order',
                'workSheetLines.months',
                'technicalSheetNeeds.activity:id,own_revenue_budget_id,code,name,sort_order',
                'technicalSheetNeeds.activityAssignments' => fn ($query) => $query->with('rule')->latest('assigned_at')->latest('id'),
                'fuelPlans.activity:id,own_revenue_budget_id,code,name,sort_order',
                'fuelPlans.activityAssignments' => fn ($query) => $query->with('rule')->latest('assigned_at')->latest('id'),
                'travelCommissions.activity:id,own_revenue_budget_id,code,name,sort_order',
                'travelCommissions.activityAssignments' => fn ($query) => $query->with('rule')->latest('assigned_at')->latest('id'),
            ])
            ->get()
            ->keyBy(fn (OwnRevenueImportFile $file): string => $file->format->value);
    }

    /**
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $workSheetLines
     * @param  EloquentCollection<int, OwnRevenueActivity>  $activities
     * @param  Collection<string, OwnRevenueActivityRule>  $rules
     * @return array<string, mixed>
     */
    private function formatData(
        OwnRevenueImportFormat $format,
        ?OwnRevenueImportFile $file,
        EloquentCollection $workSheetLines,
        EloquentCollection $activities,
        Collection $rules,
        OwnRevenueBudget $budget,
    ): array {
        $records = match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => $file?->technicalSheetNeeds ?? new EloquentCollection,
            OwnRevenueImportFormat::Fuel => $file?->fuelPlans ?? new EloquentCollection,
            OwnRevenueImportFormat::TravelExpenses => $file?->travelCommissions ?? new EloquentCollection,
            default => new EloquentCollection,
        };
        $groups = $records->groupBy(fn (OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission $record): string => $this->groupKey($format, $record));
        $groupData = $groups->map(function (Collection $group, string $groupKey) use ($format, $workSheetLines, $activities, $rules, $budget): array {
            return $this->groupData($format, $groupKey, $group, $workSheetLines, $activities, $rules, $budget);
        })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $detailCents = $this->sum($records->map(fn ($record): string => $this->recordAmount($format, $record)));
        $workSheetCents = $this->formatWorkSheetAmount($format, $records, $workSheetLines, $budget);
        $assigned = $records->whereNotNull('own_revenue_activity_id')->count();

        return [
            'format' => $format->value,
            'label' => $this->formatLabel($format),
            'file_id' => $file?->id,
            'summary' => $this->summary($records->count(), $assigned),
            'detail_cents' => $detailCents,
            'work_sheet_cents' => $workSheetCents,
            'difference_cents' => $this->subtract($detailCents, $workSheetCents),
            'groups' => $groupData->all(),
        ];
    }

    /**
     * @param  Collection<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission>  $records
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $workSheetLines
     * @param  EloquentCollection<int, OwnRevenueActivity>  $activities
     * @param  Collection<string, OwnRevenueActivityRule>  $rules
     * @return array<string, mixed>
     */
    private function groupData(
        OwnRevenueImportFormat $format,
        string $groupKey,
        Collection $records,
        EloquentCollection $workSheetLines,
        EloquentCollection $activities,
        Collection $rules,
        OwnRevenueBudget $budget,
    ): array {
        $hash = $this->groupKeys->hash($format, $groupKey);
        $candidates = $this->candidates($format, $records, $workSheetLines);
        $detailCents = $this->sum($records->map(fn ($record): string => $this->recordAmount($format, $record)));
        $workSheetCents = $this->groupWorkSheetAmount($format, $records, $workSheetLines, $budget);
        $assigned = $records->whereNotNull('own_revenue_activity_id')->count();
        $currentActivityIds = $records->pluck('own_revenue_activity_id')->filter()->unique()->values();
        $currentActivity = $currentActivityIds->count() === 1
            ? $activities->firstWhere('id', $currentActivityIds->first())
            : null;
        $rule = $rules->get($format->value.'|'.$hash);

        return [
            'hash' => $hash,
            'label' => $this->groupLabel($format, $records->first()),
            'record_count' => $records->count(),
            'summary' => $this->summary($records->count(), $assigned),
            'detail_cents' => $detailCents,
            'work_sheet_cents' => $workSheetCents,
            'difference_cents' => $this->subtract($detailCents, $workSheetCents),
            'month_evidence' => $records->pluck($format === OwnRevenueImportFormat::TechnicalSheet ? 'budget_month' : 'month')
                ->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all(),
            'candidate_activity_codes' => $candidates->pluck('code')->all(),
            'candidates' => $candidates->map(fn (OwnRevenueActivity $activity): array => $this->activityData($activity))->all(),
            'suggested_activity_id' => $candidates->count() === 1 ? $candidates->first()?->id : null,
            'current_activity' => $currentActivity === null ? null : $this->activityData($currentActivity),
            'active_rule' => $rule === null ? null : $this->ruleData($rule),
            'records' => $records->map(fn ($record): array => $this->recordData($format, $record))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission>  $records
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $workSheetLines
     * @return Collection<int, OwnRevenueActivity>
     */
    private function candidates(OwnRevenueImportFormat $format, Collection $records, EloquentCollection $workSheetLines): Collection
    {
        $itemCodes = match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => [(string) $records->first()?->specific_item_code],
            OwnRevenueImportFormat::Fuel => ['26101'],
            OwnRevenueImportFormat::TravelExpenses => $records->contains(fn (OwnRevenueTravelCommission $commission): bool => $this->rawAmount($commission, 'flight_amount_cents') !== '0')
                ? ['37501', '37101']
                : ['37501'],
            default => [],
        };

        $candidateLines = $workSheetLines->whereIn('specific_item_code', $itemCodes);
        if ($format === OwnRevenueImportFormat::TechnicalSheet) {
            $months = $records->pluck('budget_month')->map(fn ($month): int => (int) $month)->unique();
            $candidateLines = $candidateLines->filter(fn (OwnRevenueWorkSheetLine $line): bool => $months
                ->every(fn (int $month): bool => $line->months
                    ->where('month', $month)
                    ->contains(fn ($monthAmount): bool => $this->rawAmount($monthAmount, 'amount_cents') !== '0')));
        }

        return $candidateLines
            ->pluck('activity')
            ->filter()
            ->unique('id')
            ->sortBy([['sort_order', 'asc'], ['code', 'asc']])
            ->values();
    }

    /**
     * @param  Collection<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission>  $records
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $workSheetLines
     */
    private function formatWorkSheetAmount(OwnRevenueImportFormat $format, Collection $records, EloquentCollection $workSheetLines, OwnRevenueBudget $budget): string
    {
        if ($records->isEmpty()) {
            return '0';
        }

        $itemCodes = match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => $records->pluck('specific_item_code')->unique()->all(),
            OwnRevenueImportFormat::Fuel => ['26101'],
            OwnRevenueImportFormat::TravelExpenses => $records->contains(fn (OwnRevenueTravelCommission $commission): bool => $this->rawAmount($commission, 'flight_amount_cents') !== '0')
                ? ['37501', '37101']
                : ['37501'],
            default => [],
        };
        $months = $format === OwnRevenueImportFormat::Fuel
            ? [(int) $budget->fuel_budget_month]
            : $records->pluck($format === OwnRevenueImportFormat::TechnicalSheet ? 'budget_month' : 'month')->map(fn ($month): int => (int) $month)->unique()->all();

        return $this->workSheetAmount($workSheetLines, $itemCodes, $months);
    }

    /**
     * @param  Collection<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission>  $records
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $workSheetLines
     */
    private function groupWorkSheetAmount(OwnRevenueImportFormat $format, Collection $records, EloquentCollection $workSheetLines, OwnRevenueBudget $budget): string
    {
        return $this->formatWorkSheetAmount($format, $records, $workSheetLines, $budget);
    }

    /**
     * @param  EloquentCollection<int, OwnRevenueWorkSheetLine>  $lines
     * @param  list<string>  $itemCodes
     * @param  list<int>  $months
     */
    private function workSheetAmount(EloquentCollection $lines, array $itemCodes, array $months): string
    {
        return $this->sum(
            $lines->whereIn('specific_item_code', $itemCodes)
                ->flatMap(fn (OwnRevenueWorkSheetLine $line) => $line->months->whereIn('month', $months))
                ->map(fn ($month): string => $this->rawAmount($month, 'amount_cents')),
        );
    }

    private function groupKey(
        OwnRevenueImportFormat $format,
        OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission $record,
    ): string {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => $this->groupKeys->forTechnicalSheetNeed($record),
            OwnRevenueImportFormat::Fuel => $this->groupKeys->forFuelPlan($record),
            OwnRevenueImportFormat::TravelExpenses => $this->groupKeys->forTravelCommission($record),
            default => '',
        };
    }

    private function recordAmount(
        OwnRevenueImportFormat $format,
        OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission $record,
    ): string {
        if ($format === OwnRevenueImportFormat::TravelExpenses) {
            $amount = $this->amounts->add(
                $this->rawAmount($record, 'total_amount_cents'),
                $this->rawAmount($record, 'flight_amount_cents'),
            );
            if ($amount === null) {
                throw new OverflowException('El importe del registro de viáticos excede el máximo portable.');
            }

            return $amount;
        }

        return $this->rawAmount($record, 'amount_cents');
    }

    /** @return array<string, mixed> */
    private function recordData(
        OwnRevenueImportFormat $format,
        OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission $record,
    ): array {
        /** @var OwnRevenueActivityAssignment|null $assignment */
        $assignment = $record->activityAssignments->first();

        return [
            'id' => $record->id,
            'label' => $this->groupLabel($format, $record),
            'amount_cents' => $this->recordAmount($format, $record),
            'activity' => $record->activity === null ? null : $this->activityData($record->activity),
            'latest_assignment' => $assignment === null ? null : [
                'id' => $assignment->id,
                'mode' => $assignment->mode->value,
                'activity_code' => $assignment->activity_code,
                'activity_name' => $assignment->activity_name,
                'justification' => $assignment->justification->value,
                'justification_note' => $assignment->justification_note,
                'assigned_at' => $assignment->assigned_at?->toISOString(),
            ],
        ];
    }

    private function groupLabel(
        OwnRevenueImportFormat $format,
        OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission $record,
    ): string {
        if ($format === OwnRevenueImportFormat::TechnicalSheet) {
            $description = Str::of($record->description)->squish()->toString();

            return $record->specific_item_code.' · '.($description === '' ? 'Sin descripción' : $description);
        }

        $reason = Str::of($record->reason)->squish()->toString();

        return $reason === '' ? 'Sin motivo' : $reason;
    }

    /** @return array{id:int,code:string,name:string} */
    private function activityData(OwnRevenueActivity $activity): array
    {
        return ['id' => $activity->id, 'code' => $activity->code, 'name' => $activity->name];
    }

    /** @return array<string, mixed> */
    private function ruleData(OwnRevenueActivityRule $rule): array
    {
        return [
            'id' => $rule->id,
            'activity' => $rule->activity === null ? null : $this->activityData($rule->activity),
            'justification' => $rule->justification->value,
            'justification_note' => $rule->justification_note,
        ];
    }

    /** @return array{total:int,assigned:int,pending:int,complete:bool} */
    private function summary(int $total, int $assigned): array
    {
        return [
            'total' => $total,
            'assigned' => $assigned,
            'pending' => $total - $assigned,
            'complete' => $total > 0 && $assigned === $total,
        ];
    }

    /** @param Collection<int, string> $values */
    private function sum(Collection $values): string
    {
        $amount = $this->amounts->sum($values->values()->all());
        if ($amount === null) {
            throw new OverflowException('El importe acumulado de conciliación excede el máximo portable.');
        }

        return $amount;
    }

    private function rawAmount(object $model, string $attribute): string
    {
        /** @var mixed $value */
        $value = $model->getRawOriginal($attribute);

        return $this->amounts->normalize((string) $value);
    }

    private function subtract(string $left, string $right): string
    {
        $left = $this->amounts->normalize($left);
        $right = $this->amounts->normalize($right);
        $comparison = strlen($left) <=> strlen($right) ?: strcmp($left, $right);
        if ($comparison === 0) {
            return '0';
        }

        $negative = $comparison < 0;
        $larger = strrev($negative ? $right : $left);
        $smaller = strrev($negative ? $left : $right);
        $borrow = 0;
        $result = '';
        for ($index = 0, $length = strlen($larger); $index < $length; $index++) {
            $digit = (int) $larger[$index] - $borrow - (int) ($smaller[$index] ?? 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result .= (string) $digit;
        }

        $result = $this->amounts->normalize(strrev($result));

        return $negative ? '-'.$result : $result;
    }

    private function formatLabel(OwnRevenueImportFormat $format): string
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => 'Ficha técnica',
            OwnRevenueImportFormat::Fuel => 'Combustible',
            OwnRevenueImportFormat::TravelExpenses => 'Viáticos',
            default => $format->value,
        };
    }
}
