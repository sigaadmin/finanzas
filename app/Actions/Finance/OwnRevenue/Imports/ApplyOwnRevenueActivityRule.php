<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ApplyOwnRevenueActivityRule
{
    public function __construct(private readonly OwnRevenueActivityGroupKey $groupKeys) {}

    public function handle(
        Model $record,
        OwnRevenueImportFormat $format,
        OwnRevenueImportFile $file,
        User $user,
    ): Model {
        $groupKey = $this->groupKey($record, $format);
        $groupHash = $this->groupKeys->hash($format, $groupKey);
        $rule = OwnRevenueActivityRule::query()
            ->with('activity')
            ->where('own_revenue_budget_id', $file->own_revenue_budget_id)
            ->where('format', $format)
            ->where('group_hash', $groupHash)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $rule instanceof OwnRevenueActivityRule) {
            return $record;
        }

        $activity = $rule->activity;
        if ($activity === null || $activity->own_revenue_budget_id !== $file->own_revenue_budget_id) {
            throw ValidationException::withMessages([
                'file' => 'La regla de actividad vigente ya no es válida; revise la conciliación.',
            ]);
        }

        $previousActivityId = $record->getAttribute('own_revenue_activity_id');
        $record->update(['own_revenue_activity_id' => $activity->id]);

        OwnRevenueActivityAssignment::query()->create([
            'own_revenue_budget_id' => $file->own_revenue_budget_id,
            'own_revenue_import_file_id' => $file->id,
            'own_revenue_activity_rule_id' => $rule->id,
            'assignable_type' => $record->getMorphClass(),
            'assignable_id' => $record->getKey(),
            'previous_activity_id' => $previousActivityId,
            'own_revenue_activity_id' => $activity->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'mode' => OwnRevenueActivityAssignmentMode::AutomaticRule,
            'group_key' => $groupKey,
            'group_hash' => $groupHash,
            'justification' => $rule->justification,
            'justification_note' => $rule->justification_note,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        return $record->refresh();
    }

    private function groupKey(Model $record, OwnRevenueImportFormat $format): string
    {
        return match (true) {
            $format === OwnRevenueImportFormat::TechnicalSheet && $record instanceof OwnRevenueTechnicalSheetNeed => $this->groupKeys->forTechnicalSheetNeed($record),
            $format === OwnRevenueImportFormat::Fuel && $record instanceof OwnRevenueFuelPlan => $this->groupKeys->forFuelPlan($record),
            $format === OwnRevenueImportFormat::TravelExpenses && $record instanceof OwnRevenueTravelCommission => $this->groupKeys->forTravelCommission($record),
            default => throw ValidationException::withMessages(['file' => 'El formato del renglón confirmado no es válido.']),
        };
    }
}
