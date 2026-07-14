<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Exceptions\Finance\OwnRevenue\Imports\StoredImportFileUnavailable;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use App\Services\Finance\OwnRevenue\Imports\StoredImportFileHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfirmOwnRevenueSupportingImport
{
    private const FORMATS = [
        OwnRevenueImportFormat::TechnicalSheet,
        OwnRevenueImportFormat::Fuel,
        OwnRevenueImportFormat::TravelExpenses,
    ];

    public function __construct(
        private readonly CaptureOwnRevenueImportAnalysisSnapshot $captureSnapshot,
        private readonly CanonicalJson $canonicalJson,
        private readonly PortableIntegerAmount $amounts,
        private readonly StoredImportFileHasher $fileHasher,
    ) {}

    public function handle(OwnRevenueImportFile $file, User $user, string $analysisRevision): OwnRevenueImportFile
    {
        Gate::forUser($user)->authorize('confirmImports', $file->budget);

        return DB::transaction(function () use ($file, $user, $analysisRevision): OwnRevenueImportFile {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);

            Gate::forUser($user)->authorize('confirmImports', $budget);
            $this->validateFile($lockedFile, $budget, $analysisRevision);
            $this->validateIssues($lockedFile);

            $rows = $lockedFile->rows()
                ->where('row_kind', $lockedFile->format->value.'_normalized_line')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['file' => 'El análisis no contiene renglones para confirmar.']);
            }

            foreach ($rows as $row) {
                $this->createRecord($lockedFile, $budget, $row);
            }

            OwnRevenueImportFile::query()
                ->whereBelongsTo($budget, 'budget')
                ->where('format', $lockedFile->format)
                ->where('status', OwnRevenueImportFileStatus::Confirmed)
                ->whereKeyNot($lockedFile->id)
                ->lockForUpdate()
                ->get()
                ->each->update([
                    'status' => OwnRevenueImportFileStatus::Replaced,
                    'replaced_by_file_id' => $lockedFile->id,
                ]);

            $lockedFile->update([
                'status' => OwnRevenueImportFileStatus::Confirmed,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }

    private function validateFile(OwnRevenueImportFile $file, OwnRevenueBudget $budget, string $analysisRevision): void
    {
        if (! in_array($file->format, self::FORMATS, true)
            || $file->status !== OwnRevenueImportFileStatus::Ready
            || $file->analysis_token !== null
            || $file->confirmed_at !== null) {
            throw ValidationException::withMessages(['file' => 'Sólo puede confirmarse un archivo de apoyo listo.']);
        }
        if ($file->analysis_revision === null || ! hash_equals($file->analysis_revision, $analysisRevision)) {
            throw ValidationException::withMessages(['analysis_revision' => 'El análisis cambió; vuelva a revisar el archivo.']);
        }
        if ($file->budget_updated_at_at_analysis === null
            || ! $budget->updated_at->equalTo($file->budget_updated_at_at_analysis)) {
            throw ValidationException::withMessages(['file' => 'El presupuesto cambió después del análisis; vuelva a analizar el archivo.']);
        }
        $fingerprint = $this->captureSnapshot->handle($budget)->fingerprint;
        if ($file->analysis_fingerprint !== null && ! hash_equals($file->analysis_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['file' => 'Los datos de referencia cambiaron; vuelva a analizar el archivo.']);
        }
        try {
            $hash = $this->fileHasher->sha256($file->storage_disk, $file->storage_path);
        } catch (StoredImportFileUnavailable) {
            throw ValidationException::withMessages(['file' => 'No fue posible acceder al archivo almacenado; vuelva a cargarlo.']);
        }
        if (! hash_equals($file->sha256, $hash)) {
            throw ValidationException::withMessages(['file' => 'El archivo almacenado cambió; vuelva a cargarlo.']);
        }
    }

    private function validateIssues(OwnRevenueImportFile $file): void
    {
        if ($file->issues()->where('severity', OwnRevenueImportIssueSeverity::Error)->exists()) {
            throw ValidationException::withMessages(['file' => 'El análisis todavía contiene errores que deben corregirse.']);
        }
    }

    private function createRecord(OwnRevenueImportFile $file, OwnRevenueBudget $budget, OwnRevenueImportRow $row): void
    {
        $payload = $row->normalized_payload;
        if (! is_array($payload) || ! hash_equals($row->row_hash, $this->canonicalJson->hash($payload))) {
            throw ValidationException::withMessages(['file' => 'Los datos analizados ya no son íntegros.']);
        }
        $sourceRowNumber = $row->source_payload['source_rows'][0] ?? $row->source_payload['source_row'] ?? null;
        $sourceRow = is_int($sourceRowNumber)
            ? $file->rows()->where('row_number', $sourceRowNumber)->where('row_kind', $file->format->value.'_line')->first()
            : null;
        if (! $sourceRow instanceof OwnRevenueImportRow
            || ! hash_equals($sourceRow->row_hash, $this->canonicalJson->hash($sourceRow->source_payload))) {
            throw ValidationException::withMessages(['file' => 'No fue posible verificar el renglón original del archivo.']);
        }

        $base = [
            'own_revenue_budget_id' => $budget->id,
            'own_revenue_import_file_id' => $file->id,
            'own_revenue_activity_id' => null,
            'source_row_id' => $sourceRow->id,
            'sort_order' => $row->row_number,
        ];
        match ($file->format) {
            OwnRevenueImportFormat::TechnicalSheet => OwnRevenueTechnicalSheetNeed::query()->create([
                ...$base,
                'specific_item_code' => $this->string($payload, 'specificItemCode'),
                'sequence' => $this->optionalString($payload, 'sequence'),
                'quantity' => $this->string($payload, 'quantity'),
                'unit' => $this->string($payload, 'unit'),
                'description' => $this->string($payload, 'description'),
                'region_code' => '02-001',
                'region_name' => 'Felipe Carrillo Puerto',
                'amount_cents' => $this->amount($payload, 'amountCents'),
                'budget_month' => $this->month($payload, 'budgetMonth'),
            ]),
            OwnRevenueImportFormat::Fuel => OwnRevenueFuelPlan::query()->create([
                ...$base,
                'commission_date_label' => $this->optionalString($payload, 'commissionDateLabel'),
                'month' => $this->month($payload, 'month'),
                'reason' => $this->string($payload, 'reason'),
                'vehicle_model' => $this->string($payload, 'vehicleModel'),
                'kilometers_per_liter' => $this->optionalString($payload, 'kilometersPerLiter'),
                'outbound_origin' => $this->string($payload, 'outboundOrigin'),
                'outbound_destination' => $this->string($payload, 'outboundDestination'),
                'outbound_kilometers' => $this->string($payload, 'outboundKilometers'),
                'return_origin' => $this->optionalString($payload, 'returnOrigin'),
                'return_destination' => $this->optionalString($payload, 'returnDestination'),
                'return_kilometers' => $this->optionalString($payload, 'returnKilometers'),
                'liters' => $this->string($payload, 'liters'),
                'fuel_price' => $this->string($payload, 'fuelPrice'),
                'amount_cents' => $this->amount($payload, 'amountCents'),
            ]),
            OwnRevenueImportFormat::TravelExpenses => OwnRevenueTravelCommission::query()->create([
                ...$base,
                'commission_date_label' => $this->optionalString($payload, 'commissionDateLabel'),
                'month' => $this->month($payload, 'month'),
                'reason' => $this->string($payload, 'reason'),
                'person_name' => $this->string($payload, 'personName'),
                'position' => $this->string($payload, 'position'),
                'commission_days' => $this->string($payload, 'commissionDays'),
                'destination' => $this->string($payload, 'destination'),
                'per_diem_uma' => $this->string($payload, 'perDiemUma'),
                'lodging_uma' => $this->string($payload, 'lodgingUma'),
                'uma_value' => $this->string($payload, 'umaValue'),
                'per_diem_amount_cents' => $this->amount($payload, 'perDiemAmountCents'),
                'lodging_amount_cents' => $this->amount($payload, 'lodgingAmountCents'),
                'total_amount_cents' => $this->amount($payload, 'totalAmountCents'),
                'flight_amount_cents' => $this->amount($payload, 'flightAmountCents'),
            ]),
            default => throw ValidationException::withMessages(['file' => 'El formato no puede confirmarse por esta operación.']),
        };
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['file' => 'El análisis contiene un valor incompleto o inválido.']);
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function month(array $payload, string $key): int
    {
        $month = $payload[$key] ?? null;
        if (! is_int($month) || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['file' => 'El análisis contiene un mes inválido.']);
        }

        return $month;
    }

    /** @param array<string, mixed> $payload */
    private function amount(array $payload, string $key): int
    {
        $amount = $payload[$key] ?? null;
        if (! is_string($amount) || ! $this->amounts->isValid($amount)) {
            throw ValidationException::withMessages(['file' => 'El análisis contiene un importe inválido.']);
        }

        return (int) $amount;
    }
}
