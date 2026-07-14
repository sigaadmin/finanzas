<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\AbpreAnalysis;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
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

        DB::transaction(function () use ($file, $analysisToken): void {
            OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $this->ensureAnalyzable($lockedFile);
            $lockedFile->update([
                'status' => OwnRevenueImportFileStatus::Analyzing,
                'analysis_token' => $analysisToken,
            ]);
        });

        try {
            $budget = $file->budget;
            $cogMap = ExpenseClassification::query()
                ->where('fiscal_year', $budget->fiscal_year)
                ->pluck('id', 'specific_item_code')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $analysis = $this->parser->parse(
                $this->reader->read($path),
                ['fiscal_year' => $budget->fiscal_year, 'responsible_unit_code' => $budget->responsible_unit_code],
                $cogMap,
            );

            return $this->persist($file, $budget, $analysis, $analysisToken);
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return DB::transaction(function () use ($file, $exception, $analysisToken): OwnRevenueImportFile {
                $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
                $this->ensureActiveAttempt($lockedFile, $analysisToken);
                $lockedFile->issues()->delete();
                $lockedFile->issues()->create([
                    'severity' => OwnRevenueImportIssueSeverity::Error,
                    'code' => 'analysis.failed',
                    'field' => null,
                    'message' => 'No fue posible analizar el archivo XLSX.',
                    'context' => ['exception' => $exception->getMessage()],
                ]);
                $lockedFile->update([
                    'status' => OwnRevenueImportFileStatus::Failed,
                    'analysis_token' => null,
                    'analyzed_at' => now(),
                ]);

                return $lockedFile->refresh();
            });
        }
    }

    private function ensureAnalyzable(OwnRevenueImportFile $file): void
    {
        $this->ensureMutable($file);

        if ($file->format !== OwnRevenueImportFormat::Abpre) {
            throw ValidationException::withMessages([
                'file' => 'Este analizador sólo admite el formato ABPRE.',
            ]);
        }
    }

    private function ensureActiveAttempt(OwnRevenueImportFile $file, string $analysisToken): void
    {
        $this->ensureMutable($file);

        if ($file->format !== OwnRevenueImportFormat::Abpre
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
        OwnRevenueBudget $budget,
        AbpreAnalysis $analysis,
        string $analysisToken,
    ): OwnRevenueImportFile {
        return DB::transaction(function () use ($file, $budget, $analysis, $analysisToken): OwnRevenueImportFile {
            OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);
            $this->ensureActiveAttempt($lockedFile, $analysisToken);
            $lockedFile->issues()->delete();
            $lockedFile->rows()->delete();
            $rows = [];

            foreach ($analysis->sourceRows as $sourceRow) {
                $row = $lockedFile->rows()->create([
                    ...$sourceRow,
                    'row_hash' => hash('sha256', json_encode($sourceRow['source_payload'], JSON_THROW_ON_ERROR)),
                ]);
                $rows[$sourceRow['sheet_name'].'|'.$sourceRow['row_number']] = $row;
            }

            foreach ($analysis->lines as $index => $line) {
                $lockedFile->rows()->create([
                    'sheet_name' => '__normalized_abpre__',
                    'row_number' => $index + 1,
                    'row_kind' => 'abpre_line',
                    'row_hash' => hash('sha256', json_encode($line, JSON_THROW_ON_ERROR)),
                    'source_payload' => ['source_rows' => $line->sourceRows],
                    'normalized_payload' => (array) $line,
                ]);
            }

            foreach ($analysis->justifications as $index => $justification) {
                $lockedFile->rows()->create([
                    'sheet_name' => '__normalized_justifications__',
                    'row_number' => $index + 1,
                    'row_kind' => 'abpre_justification',
                    'row_hash' => hash('sha256', json_encode($justification, JSON_THROW_ON_ERROR)),
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

            $hasErrors = collect($analysis->issues)
                ->contains(fn ($issue): bool => $issue->severity === OwnRevenueImportIssueSeverity::Error);
            $lockedFile->update([
                'status' => $hasErrors ? OwnRevenueImportFileStatus::NeedsCorrection : OwnRevenueImportFileStatus::Ready,
                'analysis_token' => null,
                'budget_updated_at_at_analysis' => $budget->updated_at,
                'analyzed_at' => now(),
            ]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }
}
