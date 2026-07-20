<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\AbpreAnalysis;
use App\Data\Finance\OwnRevenue\Imports\OwnRevenueImportAnalysisSnapshot;
use App\Data\Finance\OwnRevenue\Imports\SupportingFormatAnalysis;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetAnalysis;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Exceptions\Finance\OwnRevenue\Imports\ImportAnalysisSourceChanged;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use App\Services\Finance\OwnRevenue\Imports\SupportingWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\WorkSheetWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnalyzeOwnRevenueImportFile
{
    public function __construct(
        private readonly XlsxWorkbookReader $reader,
        private readonly AbpreWorkbookParser $parser,
        private readonly WorkSheetWorkbookParser $workSheetParser,
        private readonly SupportingWorkbookParser $supportingParser = new SupportingWorkbookParser,
        private readonly ReconcileOwnRevenueWorkSheetWithAbpre $reconcileWorkSheet = new ReconcileOwnRevenueWorkSheetWithAbpre,
        private readonly CaptureOwnRevenueImportAnalysisSnapshot $captureSnapshot = new CaptureOwnRevenueImportAnalysisSnapshot,
        private readonly CanonicalJson $canonicalJson = new CanonicalJson,
    ) {}

    public function handle(OwnRevenueImportFile $file, User $user): OwnRevenueImportFile
    {
        Gate::forUser($user)->authorize('manageImports', $file->budget);

        $this->ensureAnalyzable($file);

        try {
            $path = Storage::disk($file->storage_disk)->path($file->storage_path);
            $hash = hash_file('sha256', $path);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'No fue posible acceder al archivo almacenado.',
            ]);
        }

        if ($hash === false) {
            throw ValidationException::withMessages([
                'file' => 'No fue posible acceder al archivo almacenado.',
            ]);
        }

        if ($hash !== $file->sha256) {
            throw ValidationException::withMessages(['file' => 'La huella del archivo almacenado no coincide.']);
        }

        $analysisToken = (string) Str::uuid();

        $attempt = DB::transaction(function () use ($file, $analysisToken): array {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $this->ensureAnalyzable($lockedFile);
            $snapshot = $this->captureSnapshot->handle($lockedBudget);
            $lockedFile->update([
                'status' => OwnRevenueImportFileStatus::Analyzing,
                'analysis_token' => $analysisToken,
            ]);

            return [
                'format' => $lockedFile->format,
                'snapshot' => $snapshot,
                'detected_year' => $lockedFile->detected_year,
            ];
        });
        $format = $attempt['format'];
        $snapshot = $attempt['snapshot'];
        $detectedYear = $attempt['detected_year'];

        try {
            $workbook = $this->reader->read($path);
            $analysis = match ($format) {
                OwnRevenueImportFormat::Abpre => $this->parser->parse(
                    $workbook,
                    [
                        'fiscal_year' => (int) $snapshot->budget['fiscal_year'],
                        'responsible_unit_code' => (string) $snapshot->budget['responsible_unit_code'],
                    ],
                    $snapshot->cogMap,
                ),
                OwnRevenueImportFormat::WorkSheet => $this->workSheetParser->parse(
                    $workbook,
                    $snapshot->activityMap,
                    $snapshot->cogMap,
                ),
                OwnRevenueImportFormat::TechnicalSheet,
                OwnRevenueImportFormat::Fuel,
                OwnRevenueImportFormat::TravelExpenses => $this->supportingParser->parse(
                    $workbook,
                    $format,
                    $snapshot->cogMap,
                    (int) $snapshot->budget['fiscal_year'],
                    $detectedYear,
                    $snapshot->activityMap,
                ),
            };

            return $this->persist($file, $analysis, $analysisToken, $format, $snapshot);
        } catch (Throwable $exception) {
            if ($exception instanceof ImportAnalysisSourceChanged) {
                return $this->failAttempt(
                    $file,
                    $analysisToken,
                    $format,
                    'analysis.source_changed',
                    'El presupuesto o sus catálogos cambiaron durante el análisis; vuelva a analizar el archivo.',
                    [],
                );
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return $this->failAttempt(
                $file,
                $analysisToken,
                $format,
                'analysis.failed',
                'No fue posible analizar el archivo XLSX.',
                ['exception' => $exception->getMessage()],
            );
        }
    }

    private function ensureAnalyzable(OwnRevenueImportFile $file): void
    {
        $this->ensureMutable($file);

        if ($file->format === null) {
            throw ValidationException::withMessages([
                'file' => 'Clasifique el archivo antes de analizarlo.',
            ]);
        }
    }

    private function ensureActiveAttempt(
        OwnRevenueImportFile $file,
        string $analysisToken,
        OwnRevenueImportFormat $format,
    ): void {
        $this->ensureMutable($file);

        if ($file->format !== $format
            || $file->status !== OwnRevenueImportFileStatus::Analyzing
            || $file->analysis_token === null
            || ! hash_equals($file->analysis_token, $analysisToken)) {
            throw ValidationException::withMessages([
                'file' => 'El intento de análisis ya no está vigente.',
            ]);
        }
    }

    private function ensureMutable(OwnRevenueImportFile $file): void
    {
        if ($file->status === OwnRevenueImportFileStatus::Confirmed
            || $file->status === OwnRevenueImportFileStatus::Discarded
            || $file->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'file' => 'No se puede analizar un archivo confirmado o descartado.',
            ]);
        }
    }

    private function persist(
        OwnRevenueImportFile $file,
        AbpreAnalysis|WorkSheetAnalysis|SupportingFormatAnalysis $analysis,
        string $analysisToken,
        OwnRevenueImportFormat $format,
        OwnRevenueImportAnalysisSnapshot $snapshot,
    ): OwnRevenueImportFile {
        return DB::transaction(function () use ($file, $analysis, $analysisToken, $format, $snapshot): OwnRevenueImportFile {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $this->ensureActiveAttempt($lockedFile, $analysisToken, $format);
            $currentSnapshot = $this->captureSnapshot->handle($lockedBudget);
            if (! hash_equals($snapshot->fingerprint, $currentSnapshot->fingerprint)) {
                throw new ImportAnalysisSourceChanged;
            }
            $lockedFile->issues()->delete();
            $lockedFile->rows()->delete();
            $rows = [];

            foreach ($analysis->sourceRows as $sourceRow) {
                $row = $lockedFile->rows()->create([
                    ...$sourceRow,
                    'row_hash' => $this->canonicalJson->hash($sourceRow['source_payload']),
                ]);
                $rows[$sourceRow['sheet_name'].'|'.$sourceRow['row_number']] = $row;
            }

            $normalizedSheet = '__normalized_'.$format->value.'__';
            $normalizedRowKind = match ($format) {
                OwnRevenueImportFormat::Abpre => 'abpre_line',
                OwnRevenueImportFormat::WorkSheet => 'work_sheet_normalized_line',
                default => $format->value.'_normalized_line',
            };

            foreach ($analysis->lines as $index => $line) {
                $isSupporting = $analysis instanceof SupportingFormatAnalysis;
                $normalizedPayload = $isSupporting ? $line->values : (array) $line;
                $lockedFile->rows()->create([
                    'sheet_name' => $normalizedSheet,
                    'row_number' => $index + 1,
                    'row_kind' => $normalizedRowKind,
                    'row_hash' => $this->canonicalJson->hash($normalizedPayload),
                    'source_payload' => ['source_rows' => $isSupporting ? [$line->sourceRow] : $line->sourceRows],
                    'normalized_payload' => $normalizedPayload,
                ]);
            }

            foreach ($analysis instanceof AbpreAnalysis ? $analysis->justifications : [] as $index => $justification) {
                $lockedFile->rows()->create([
                    'sheet_name' => '__normalized_justifications__',
                    'row_number' => $index + 1,
                    'row_kind' => 'abpre_justification',
                    'row_hash' => $this->canonicalJson->hash((array) $justification),
                    'source_payload' => ['source_row' => $justification->sourceRow],
                    'normalized_payload' => (array) $justification,
                ]);
            }

            foreach ($analysis->issues as $issue) {
                $row = $issue->sheetName !== null && $issue->rowNumber !== null
                    ? ($rows[$issue->sheetName.'|'.$issue->rowNumber] ?? null)
                    : null;
                $lockedFile->issues()->create([
                    'own_revenue_import_row_id' => $row?->id,
                    'severity' => $issue->severity,
                    'code' => $issue->code,
                    'field' => $issue->field,
                    'message' => $issue->message,
                    'context' => $issue->context,
                ]);
            }

            if ($analysis instanceof WorkSheetAnalysis) {
                foreach ($this->reconcileWorkSheet->handle($lockedBudget, $analysis) as $issue) {
                    $lockedFile->issues()->create([
                        'own_revenue_import_row_id' => null,
                        'severity' => $issue->severity,
                        'code' => $issue->code,
                        'field' => $issue->field,
                        'message' => $issue->message,
                        'context' => $issue->context,
                    ]);
                }
            }

            if ($analysis->lines === []) {
                $isAbpre = $format === OwnRevenueImportFormat::Abpre;
                $isWorkSheet = $format === OwnRevenueImportFormat::WorkSheet;
                $lockedFile->issues()->create([
                    'own_revenue_import_row_id' => null,
                    'severity' => OwnRevenueImportIssueSeverity::Error,
                    'code' => $isAbpre
                        ? 'abpre.no_importable_lines'
                        : ($isWorkSheet ? 'work_sheet.no_importable_lines' : $format->value.'.no_importable_lines'),
                    'field' => null,
                    'message' => $isAbpre
                        ? 'El análisis no contiene líneas ABPRE importables.'
                        : ($isWorkSheet
                            ? 'El análisis no contiene renglones importables de la Hoja de trabajo.'
                            : 'El análisis no contiene renglones importables del formato seleccionado.'),
                    'context' => [],
                ]);
            }

            $hasErrors = $analysis->lines === [] || $lockedFile->issues()
                ->where('severity', OwnRevenueImportIssueSeverity::Error)
                ->exists();
            $lockedFile->update([
                'status' => $hasErrors ? OwnRevenueImportFileStatus::NeedsCorrection : OwnRevenueImportFileStatus::Ready,
                'analysis_token' => null,
                'analysis_revision' => (string) Str::uuid(),
                'analysis_fingerprint' => $snapshot->fingerprint,
                'abpre_import_file_id_at_analysis' => $format === OwnRevenueImportFormat::WorkSheet
                    ? OwnRevenueImportFile::query()
                        ->whereBelongsTo($lockedBudget, 'budget')
                        ->where('format', OwnRevenueImportFormat::Abpre)
                        ->where('status', OwnRevenueImportFileStatus::Confirmed)
                        ->latest('confirmed_at')
                        ->latest('id')
                        ->value('id')
                    : null,
                'budget_updated_at_at_analysis' => $lockedBudget->updated_at,
                'analyzed_at' => now(),
            ]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $context */
    private function failAttempt(
        OwnRevenueImportFile $file,
        string $analysisToken,
        OwnRevenueImportFormat $format,
        string $code,
        string $message,
        array $context,
    ): OwnRevenueImportFile {
        return DB::transaction(function () use ($file, $analysisToken, $format, $code, $message, $context): OwnRevenueImportFile {
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $this->ensureActiveAttempt($lockedFile, $analysisToken, $format);
            $lockedFile->issues()->updateOrCreate(
                ['own_revenue_import_row_id' => null, 'code' => $code],
                [
                    'severity' => OwnRevenueImportIssueSeverity::Error,
                    'field' => null,
                    'message' => $message,
                    'context' => $context,
                ],
            );
            $lockedFile->update([
                'status' => OwnRevenueImportFileStatus::Failed,
                'analysis_token' => null,
            ]);

            return $lockedFile->refresh();
        });
    }
}
