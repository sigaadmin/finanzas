<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreOwnRevenueActivityRule
{
    public function __construct(private readonly OwnRevenueActivityGroupKey $groupKeys) {}

    public function handle(
        OwnRevenueBudget $budget,
        User $user,
        OwnRevenueImportFormat $format,
        string $groupHash,
        int $activityId,
        OwnRevenueActivityJustification $justification,
        ?string $justificationNote,
        int $expectedWorkSheetFileId,
        int $expectedSupportingFileId,
    ): OwnRevenueActivityRule {
        Gate::forUser($user)->authorize('confirmImports', $budget);

        return DB::transaction(function () use (
            $budget,
            $user,
            $format,
            $groupHash,
            $activityId,
            $justification,
            $justificationNote,
            $expectedWorkSheetFileId,
            $expectedSupportingFileId,
        ): OwnRevenueActivityRule {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('confirmImports', $lockedBudget);

            $files = OwnRevenueImportFile::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereIn('format', [OwnRevenueImportFormat::WorkSheet, $format])
                ->lockForUpdate()
                ->get();
            $workSheetFile = $this->currentConfirmedFile($files, OwnRevenueImportFormat::WorkSheet);
            $supportingFile = $this->currentConfirmedFile($files, $format);

            $this->ensureCurrentFile($workSheetFile, $expectedWorkSheetFileId, 'expected_work_sheet_file_id');
            $this->ensureCurrentFile($supportingFile, $expectedSupportingFileId, 'expected_supporting_file_id');

            $records = $this->recordsForFile($supportingFile, $format);
            $matchingRecords = $records->filter(function (Model $record) use ($format, $groupHash): bool {
                $groupKey = $this->groupKey($format, $record);

                return hash_equals($groupHash, $this->groupKeys->hash($format, $groupKey));
            });
            if ($matchingRecords->isEmpty()) {
                $this->stale('group_hash');
            }

            $groupKey = $this->groupKey($format, $matchingRecords->first());
            $activity = OwnRevenueActivity::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->lockForUpdate()
                ->find($activityId);
            if (! $activity instanceof OwnRevenueActivity) {
                throw ValidationException::withMessages([
                    'activity_id' => 'La actividad seleccionada no pertenece a este presupuesto.',
                ]);
            }

            $previousRule = OwnRevenueActivityRule::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->where('format', $format)
                ->where('group_hash', $groupHash)
                ->where('is_active', true)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($previousRule instanceof OwnRevenueActivityRule) {
                $previousRule->update([
                    'is_active' => false,
                    'deactivated_by' => $user->id,
                    'deactivated_at' => now(),
                ]);
            }

            $rule = OwnRevenueActivityRule::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'format' => $format,
                'group_key' => $groupKey,
                'group_hash' => $groupHash,
                'group_payload' => $this->groupPayload($format, $matchingRecords->first()),
                'own_revenue_activity_id' => $activity->id,
                'activity_code' => $activity->code,
                'activity_name' => $activity->name,
                'justification' => $justification,
                'justification_note' => $justificationNote,
                'created_by' => $user->id,
                'is_active' => true,
                'replaces_rule_id' => $previousRule?->id,
            ]);

            foreach ($matchingRecords as $record) {
                $previousActivityId = $record->own_revenue_activity_id;
                if ($previousActivityId === $activity->id) {
                    continue;
                }

                $record->update(['own_revenue_activity_id' => $activity->id]);
                $rule->assignments()->create([
                    'own_revenue_budget_id' => $lockedBudget->id,
                    'own_revenue_import_file_id' => $supportingFile->id,
                    'assignable_type' => $record->getMorphClass(),
                    'assignable_id' => $record->getKey(),
                    'previous_activity_id' => $previousActivityId,
                    'own_revenue_activity_id' => $activity->id,
                    'activity_code' => $activity->code,
                    'activity_name' => $activity->name,
                    'mode' => OwnRevenueActivityAssignmentMode::GroupRule,
                    'group_key' => $groupKey,
                    'group_hash' => $groupHash,
                    'justification' => $justification,
                    'justification_note' => $justificationNote,
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                ]);
            }

            return $rule->refresh();
        }, attempts: 3);
    }

    /** @param Collection<int, OwnRevenueImportFile> $files */
    private function currentConfirmedFile(Collection $files, OwnRevenueImportFormat $format): ?OwnRevenueImportFile
    {
        return $files
            ->where('format', $format)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->sortByDesc(fn (OwnRevenueImportFile $file): string => sprintf(
                '%020d-%020d',
                $file->confirmed_at?->getTimestamp() ?? 0,
                $file->id,
            ))
            ->first();
    }

    private function ensureCurrentFile(?OwnRevenueImportFile $file, int $expectedId, string $field): void
    {
        if (! $file instanceof OwnRevenueImportFile || $file->id !== $expectedId) {
            $this->stale($field);
        }
    }

    private function stale(string $field): never
    {
        throw ValidationException::withMessages([
            $field => 'Los archivos confirmados cambiaron; actualiza la página antes de continuar.',
        ]);
    }

    /** @return Collection<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission> */
    private function recordsForFile(OwnRevenueImportFile $file, OwnRevenueImportFormat $format): Collection
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => $file->technicalSheetNeeds()->lockForUpdate()->get(),
            OwnRevenueImportFormat::Fuel => $file->fuelPlans()->lockForUpdate()->get(),
            OwnRevenueImportFormat::TravelExpenses => $file->travelCommissions()->lockForUpdate()->get(),
            default => throw ValidationException::withMessages(['format' => 'El formato no admite conciliación.']),
        };
    }

    private function groupKey(OwnRevenueImportFormat $format, Model $record): string
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => $this->groupKeys->forTechnicalSheetNeed($record),
            OwnRevenueImportFormat::Fuel => $this->groupKeys->forFuelPlan($record),
            OwnRevenueImportFormat::TravelExpenses => $this->groupKeys->forTravelCommission($record),
            default => '',
        };
    }

    /** @return array<string, string> */
    private function groupPayload(OwnRevenueImportFormat $format, Model $record): array
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => [
                'specific_item_code' => $record->specific_item_code,
                'description' => Str::of($record->description)->squish()->toString(),
            ],
            OwnRevenueImportFormat::Fuel, OwnRevenueImportFormat::TravelExpenses => [
                'reason' => Str::of($record->reason)->squish()->toString(),
            ],
            default => [],
        };
    }
}
