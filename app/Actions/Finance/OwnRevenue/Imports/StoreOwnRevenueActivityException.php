<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
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
use Illuminate\Validation\ValidationException;

class StoreOwnRevenueActivityException
{
    public function __construct(private readonly OwnRevenueActivityGroupKey $groupKeys) {}

    public function handle(
        OwnRevenueBudget $budget,
        User $user,
        OwnRevenueImportFormat $format,
        int $recordId,
        int $activityId,
        OwnRevenueActivityJustification $justification,
        ?string $justificationNote,
        int $expectedWorkSheetFileId,
        int $expectedSupportingFileId,
    ): OwnRevenueActivityAssignment {
        Gate::forUser($user)->authorize('confirmImports', $budget);

        return DB::transaction(function () use (
            $budget,
            $user,
            $format,
            $recordId,
            $activityId,
            $justification,
            $justificationNote,
            $expectedWorkSheetFileId,
            $expectedSupportingFileId,
        ): OwnRevenueActivityAssignment {
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

            $activity = OwnRevenueActivity::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->lockForUpdate()
                ->find($activityId);
            if (! $activity instanceof OwnRevenueActivity) {
                throw ValidationException::withMessages([
                    'activity_id' => 'La actividad seleccionada no pertenece a este presupuesto.',
                ]);
            }

            $record = $this->findCurrentRecord($lockedBudget, $supportingFile, $format, $recordId);
            $groupKey = $this->groupKey($format, $record);
            $previousActivityId = $record->own_revenue_activity_id;

            $record->update(['own_revenue_activity_id' => $activity->id]);

            return OwnRevenueActivityAssignment::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'own_revenue_import_file_id' => $supportingFile->id,
                'own_revenue_activity_rule_id' => null,
                'assignable_type' => $record->getMorphClass(),
                'assignable_id' => $record->getKey(),
                'previous_activity_id' => $previousActivityId,
                'own_revenue_activity_id' => $activity->id,
                'activity_code' => $activity->code,
                'activity_name' => $activity->name,
                'mode' => OwnRevenueActivityAssignmentMode::IndividualException,
                'group_key' => $groupKey,
                'group_hash' => $this->groupKeys->hash($format, $groupKey),
                'justification' => $justification,
                'justification_note' => $justificationNote,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);
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
            throw ValidationException::withMessages([
                $field => 'Los archivos confirmados cambiaron; actualiza la página antes de continuar.',
            ]);
        }
    }

    private function findCurrentRecord(
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $file,
        OwnRevenueImportFormat $format,
        int $recordId,
    ): Model {
        $model = match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => OwnRevenueTechnicalSheetNeed::class,
            OwnRevenueImportFormat::Fuel => OwnRevenueFuelPlan::class,
            OwnRevenueImportFormat::TravelExpenses => OwnRevenueTravelCommission::class,
            default => throw ValidationException::withMessages(['format' => 'El formato no admite conciliación.']),
        };

        $record = $model::query()
            ->whereBelongsTo($budget, 'budget')
            ->whereBelongsTo($file, 'file')
            ->lockForUpdate()
            ->find($recordId);

        if (! $record instanceof Model) {
            throw ValidationException::withMessages([
                'record' => 'El registro no pertenece al archivo complementario confirmado vigente.',
            ]);
        }

        return $record;
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
}
