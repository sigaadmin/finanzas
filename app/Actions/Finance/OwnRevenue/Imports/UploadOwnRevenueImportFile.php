<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\InvalidXlsxWorkbookException;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueWorkbookFormatDetector;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UploadOwnRevenueImportFile
{
    public function __construct(
        private readonly XlsxWorkbookReader $reader,
        private readonly OwnRevenueWorkbookFormatDetector $detector,
    ) {}

    public function handle(
        OwnRevenueImportSession $session,
        User $user,
        UploadedFile $upload,
        bool $forceReanalysis,
    ): OwnRevenueImportFile {
        $budget = $session->budget;
        Gate::forUser($user)->authorize('manageImports', $budget);

        if ($session->status !== OwnRevenueImportSessionStatus::Open) {
            throw ValidationException::withMessages(['session' => 'La sesión de importación ya no está abierta.']);
        }

        if (! $upload->isValid()) {
            throw ValidationException::withMessages(['file' => 'El archivo no se cargó correctamente.']);
        }

        $realPath = $upload->getRealPath();
        $sha256 = hash_file('sha256', $realPath);

        if ($sha256 === false) {
            throw new RuntimeException('No se pudo calcular la huella del archivo XLSX.');
        }

        try {
            $detection = $this->detector->detect($this->reader->read($realPath));
        } catch (InvalidXlsxWorkbookException) {
            throw ValidationException::withMessages([
                'file' => 'El archivo XLSX no es válido o excede los límites de procesamiento.',
            ]);
        }

        $storagePath = $upload->store(
            "own-revenue/imports/{$budget->id}/{$session->id}",
            'local',
        );

        if ($storagePath === false) {
            throw new RuntimeException('No se pudo almacenar el archivo XLSX.');
        }

        try {
            return DB::transaction(function () use (
                $session,
                $budget,
                $user,
                $upload,
                $forceReanalysis,
                $sha256,
                $detection,
                $storagePath,
            ): OwnRevenueImportFile {
                OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);

                $duplicateExists = OwnRevenueImportFile::query()
                    ->whereBelongsTo($budget, 'budget')
                    ->where('sha256', $sha256)
                    ->exists();

                if ($duplicateExists && ! $forceReanalysis) {
                    throw ValidationException::withMessages([
                        'file' => 'Este archivo ya fue cargado. Confirme si desea volver a analizarlo.',
                    ]);
                }

                $versionNumber = $detection->format === null
                    ? 1
                    : $this->nextVersionNumber($budget, $detection->format);

                return OwnRevenueImportFile::query()->create([
                    'own_revenue_import_session_id' => $session->id,
                    'own_revenue_budget_id' => $budget->id,
                    'uploaded_by' => $user->id,
                    'format' => $detection->format,
                    'detected_format' => $detection->format,
                    'detected_year' => $detection->detectedYear,
                    'original_name' => $upload->getClientOriginalName(),
                    'storage_disk' => 'local',
                    'storage_path' => $storagePath,
                    'size_bytes' => $upload->getSize(),
                    'sha256' => $sha256,
                    'version_number' => $versionNumber,
                    'status' => $this->initialStatus($detection->format),
                    'detection_confidence' => $detection->confidence,
                    'detection_evidence' => $detection->evidence,
                ]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storagePath);

            throw $exception;
        }
    }

    private function nextVersionNumber(OwnRevenueBudget $budget, OwnRevenueImportFormat $format): int
    {
        return ((int) OwnRevenueImportFile::query()
            ->whereBelongsTo($budget, 'budget')
            ->where('format', $format)
            ->max('version_number')) + 1;
    }

    private function initialStatus(?OwnRevenueImportFormat $format): OwnRevenueImportFileStatus
    {
        return match ($format) {
            null => OwnRevenueImportFileStatus::NeedsCorrection,
            OwnRevenueImportFormat::Abpre => OwnRevenueImportFileStatus::Uploaded,
            default => OwnRevenueImportFileStatus::ParserPending,
        };
    }
}
