<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\OwnRevenueImportAnalysisSnapshot;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;

class CaptureOwnRevenueImportAnalysisSnapshot
{
    public function __construct(
        private readonly CanonicalJson $canonicalJson = new CanonicalJson,
    ) {}

    public function handle(OwnRevenueBudget $budget): OwnRevenueImportAnalysisSnapshot
    {
        $activities = $budget->activities()
            ->orderBy('id')
            ->get()
            ->map(fn (OwnRevenueActivity $activity): array => $activity->getAttributes())
            ->all();
        $classifications = ExpenseClassification::query()
            ->where('fiscal_year', $budget->fiscal_year)
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseClassification $classification): array => $classification->getAttributes())
            ->all();
        $confirmedAbpre = OwnRevenueImportFile::query()
            ->whereBelongsTo($budget, 'budget')
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->latest('confirmed_at')
            ->latest('id')
            ->first();
        $abpre = $confirmedAbpre === null ? null : [
            'file' => $confirmedAbpre->getAttributes(),
            'lines' => $confirmedAbpre->abpreLines()
                ->orderBy('id')
                ->get()
                ->map(fn (OwnRevenueAbpreLine $line): array => $line->getAttributes())
                ->all(),
        ];
        $source = [
            'budget' => $budget->getAttributes(),
            'activities' => $activities,
            'classifications' => $classifications,
            'confirmed_abpre' => $abpre,
        ];

        return new OwnRevenueImportAnalysisSnapshot(
            fingerprint: $this->canonicalJson->hash($source),
            budget: $budget->getAttributes(),
            activityMap: collect($activities)->pluck('id', 'code')->map(fn (mixed $id): int => (int) $id)->all(),
            cogMap: collect($classifications)->pluck('id', 'specific_item_code')->map(fn (mixed $id): int => (int) $id)->all(),
        );
    }
}
