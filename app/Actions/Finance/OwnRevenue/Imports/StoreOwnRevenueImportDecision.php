<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreOwnRevenueImportDecision
{
    private const ALLOWED_WARNING_CODES = [
        'work_sheet.abpre_mismatch',
        'year.mismatch',
        'region.normalized',
    ];

    public function __construct(private readonly CaptureOwnRevenueImportAnalysisSnapshot $captureSnapshot) {}

    public function handle(
        OwnRevenueImportFile $file,
        OwnRevenueImportIssue $issue,
        User $user,
        string $analysisRevision,
        string $decision,
        ?string $justification,
    ): OwnRevenueImportIssue {
        Gate::forUser($user)->authorize('confirmImports', $file->budget);

        return DB::transaction(function () use ($file, $issue, $user, $analysisRevision, $decision, $justification): OwnRevenueImportIssue {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $lockedIssue = OwnRevenueImportIssue::query()
                ->where('own_revenue_import_file_id', $lockedFile->id)
                ->lockForUpdate()
                ->findOrFail($issue->id);

            Gate::forUser($user)->authorize('confirmImports', $lockedBudget);
            if ($lockedFile->budget_updated_at_at_analysis === null
                || ! $lockedBudget->updated_at->equalTo($lockedFile->budget_updated_at_at_analysis)) {
                throw ValidationException::withMessages([
                    'file' => 'El presupuesto cambió después del análisis; vuelva a analizar el archivo.',
                ]);
            }
            $this->validateCurrentAnalysis($lockedFile, $lockedIssue, $analysisRevision);
            $fingerprint = $this->captureSnapshot->handle($lockedBudget)->fingerprint;
            if ($lockedFile->analysis_fingerprint === null
                || ! hash_equals($lockedFile->analysis_fingerprint, $fingerprint)) {
                throw ValidationException::withMessages([
                    'file' => 'Los datos de referencia cambiaron; vuelva a analizar el archivo.',
                ]);
            }
            $lockedIssue->decisions()->delete();
            $lockedIssue->decisions()->create([
                'own_revenue_import_row_id' => $lockedIssue->own_revenue_import_row_id,
                'current_value' => $lockedIssue->context,
                'proposed_value' => null,
                'resolved_value' => [
                    'accepted' => $decision === 'accepted',
                    'analysis_revision' => $analysisRevision,
                ],
                'resolution' => $decision,
                'justification' => $justification,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);

            return $lockedIssue->refresh();
        }, attempts: 3);
    }

    private function validateCurrentAnalysis(
        OwnRevenueImportFile $file,
        OwnRevenueImportIssue $issue,
        string $analysisRevision,
    ): void {
        $validStatus = in_array($file->status, [
            OwnRevenueImportFileStatus::Ready,
            OwnRevenueImportFileStatus::NeedsCorrection,
        ], true);
        $currentRevision = $file->analysis_revision;

        if (! $validStatus
            || $file->analysis_token !== null
            || $currentRevision === null
            || ! hash_equals($currentRevision, $analysisRevision)) {
            throw ValidationException::withMessages([
                'analysis_revision' => 'El análisis cambió; vuelva a revisar la Hoja de trabajo.',
            ]);
        }

        if ($issue->severity !== OwnRevenueImportIssueSeverity::Warning
            || ! in_array($issue->code, self::ALLOWED_WARNING_CODES, true)) {
            throw ValidationException::withMessages([
                'decision' => 'Esta incidencia no admite una decisión explícita.',
            ]);
        }

        if ($issue->code !== 'work_sheet.abpre_mismatch') {
            return;
        }

        $currentAbpreId = OwnRevenueImportFile::query()
            ->where('own_revenue_budget_id', $file->own_revenue_budget_id)
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->latest('confirmed_at')
            ->latest('id')
            ->value('id');

        if ($currentAbpreId === null || $currentAbpreId !== ($issue->context['abpre_import_file_id'] ?? null)) {
            throw ValidationException::withMessages([
                'analysis_revision' => 'El ABPRE confirmado cambió; vuelva a analizar la Hoja de trabajo.',
            ]);
        }
    }
}
