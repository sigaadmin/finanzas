<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Exceptions\Finance\OwnRevenue\Imports\StoredImportFileUnavailable;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportDecision;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use App\Services\Finance\OwnRevenue\Imports\StoredImportFileHasher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfirmOwnRevenueWorkSheetImport
{
    public function __construct(
        private readonly CaptureOwnRevenueImportAnalysisSnapshot $captureSnapshot,
        private readonly CanonicalJson $canonicalJson,
        private readonly PortableIntegerAmount $amounts,
        private readonly StoredImportFileHasher $fileHasher,
    ) {}

    public function handle(
        OwnRevenueImportFile $file,
        User $user,
        string $analysisRevision,
    ): OwnRevenueImportFile {
        Gate::forUser($user)->authorize('confirmImports', $file->budget);

        return DB::transaction(function () use ($file, $user, $analysisRevision): OwnRevenueImportFile {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);

            Gate::forUser($user)->authorize('confirmImports', $budget);
            $this->validateAnalysisIdentity($lockedFile, $budget, $analysisRevision);

            $issues = $lockedFile->issues()->orderBy('id')->lockForUpdate()->get();
            $decisions = OwnRevenueImportDecision::query()
                ->whereIn('own_revenue_import_issue_id', $issues->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $this->validateIssues($lockedFile, $issues, $decisions);

            $currentAbpre = OwnRevenueImportFile::query()
                ->where('own_revenue_budget_id', $budget->id)
                ->where('format', OwnRevenueImportFormat::Abpre)
                ->where('status', OwnRevenueImportFileStatus::Confirmed)
                ->latest('confirmed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $previousWorkSheets = OwnRevenueImportFile::query()
                ->where('own_revenue_budget_id', $budget->id)
                ->where('format', OwnRevenueImportFormat::WorkSheet)
                ->where('status', OwnRevenueImportFileStatus::Confirmed)
                ->whereKeyNot($lockedFile->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->validateCurrentSources($lockedFile, $budget, $currentAbpre);
            $normalizedRows = $lockedFile->rows()
                ->where('row_kind', 'work_sheet_normalized_line')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();
            if ($normalizedRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'file' => 'El análisis no contiene renglones normalizados de la Hoja de trabajo.',
                ]);
            }

            $sourceRows = $lockedFile->rows()
                ->where('row_kind', 'work_sheet_line')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get()
                ->keyBy('row_number');
            $activities = $budget->activities()->get()->keyBy('code');
            $classifications = ExpenseClassification::query()
                ->where('fiscal_year', $budget->fiscal_year)
                ->get()
                ->keyBy('specific_item_code');

            foreach ($normalizedRows as $normalizedRow) {
                $this->createLine(
                    $lockedFile,
                    $budget,
                    $normalizedRow,
                    $sourceRows,
                    $activities,
                    $classifications,
                );
            }

            foreach ($previousWorkSheets as $previousWorkSheet) {
                $previousWorkSheet->update([
                    'status' => OwnRevenueImportFileStatus::Replaced,
                    'replaced_by_file_id' => $lockedFile->id,
                ]);
            }
            $lockedFile->update([
                'status' => OwnRevenueImportFileStatus::Confirmed,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }

    private function validateAnalysisIdentity(
        OwnRevenueImportFile $file,
        OwnRevenueBudget $budget,
        string $analysisRevision,
    ): void {
        if ($file->format !== OwnRevenueImportFormat::WorkSheet
            || $file->status !== OwnRevenueImportFileStatus::Ready
            || $file->analysis_token !== null
            || $file->confirmed_at !== null) {
            throw ValidationException::withMessages([
                'file' => 'Sólo puede confirmarse una Hoja de trabajo lista y no confirmada previamente.',
            ]);
        }

        if ($file->analysis_revision === null || ! hash_equals($file->analysis_revision, $analysisRevision)) {
            throw ValidationException::withMessages([
                'analysis_revision' => 'El análisis cambió; vuelva a revisar la Hoja de trabajo.',
            ]);
        }

        if ($file->budget_updated_at_at_analysis === null
            || ! $budget->updated_at->equalTo($file->budget_updated_at_at_analysis)) {
            throw ValidationException::withMessages([
                'file' => 'El presupuesto cambió después del análisis; vuelva a analizar la Hoja de trabajo.',
            ]);
        }

        try {
            $hash = $this->fileHasher->sha256($file->storage_disk, $file->storage_path);
        } catch (StoredImportFileUnavailable) {
            throw ValidationException::withMessages([
                'file' => 'No fue posible acceder al archivo almacenado; vuelva a cargar la Hoja de trabajo.',
            ]);
        }
        if (! hash_equals($file->sha256, $hash)) {
            throw ValidationException::withMessages([
                'file' => 'El archivo almacenado cambió; vuelva a cargar la Hoja de trabajo.',
            ]);
        }
    }

    /**
     * @param  Collection<int, OwnRevenueImportIssue>  $issues
     * @param  Collection<int, OwnRevenueImportDecision>  $decisions
     */
    private function validateIssues(
        OwnRevenueImportFile $file,
        Collection $issues,
        Collection $decisions,
    ): void {
        if ($issues->contains('severity', OwnRevenueImportIssueSeverity::Error)) {
            throw ValidationException::withMessages([
                'file' => 'El análisis todavía contiene errores que deben corregirse.',
            ]);
        }

        foreach ($issues as $issue) {
            if ($issue->severity !== OwnRevenueImportIssueSeverity::Warning
                || ($issue->context['requires_decision'] ?? false) !== true) {
                continue;
            }

            $decision = $decisions->where('own_revenue_import_issue_id', $issue->id)->last();
            $resolvedValue = $decision?->resolved_value;
            $isCurrentAcceptance = $decision?->resolution === 'accepted'
                && ($resolvedValue['accepted'] ?? false) === true
                && is_string($resolvedValue['analysis_revision'] ?? null)
                && hash_equals($file->analysis_revision, $resolvedValue['analysis_revision']);
            if (! $isCurrentAcceptance) {
                throw ValidationException::withMessages([
                    'decisions' => 'Debe aceptar todas las advertencias requeridas para este análisis.',
                ]);
            }
        }
    }

    private function validateCurrentSources(
        OwnRevenueImportFile $file,
        OwnRevenueBudget $budget,
        ?OwnRevenueImportFile $currentAbpre,
    ): void {
        if ($currentAbpre === null || $file->abpre_import_file_id_at_analysis !== $currentAbpre->id) {
            throw ValidationException::withMessages([
                'analysis_revision' => 'El ABPRE confirmado cambió; vuelva a analizar la Hoja de trabajo.',
            ]);
        }

        $currentFingerprint = $this->captureSnapshot->handle($budget)->fingerprint;
        if ($file->analysis_fingerprint === null || ! hash_equals($file->analysis_fingerprint, $currentFingerprint)) {
            throw ValidationException::withMessages([
                'analysis_revision' => 'El presupuesto, sus catálogos o el ABPRE cambiaron; vuelva a analizar la Hoja de trabajo.',
            ]);
        }
    }

    /**
     * @param  Collection<int, OwnRevenueImportRow>  $sourceRows
     * @param  Collection<string, OwnRevenueActivity>  $activities
     * @param  Collection<string, ExpenseClassification>  $classifications
     */
    private function createLine(
        OwnRevenueImportFile $file,
        OwnRevenueBudget $budget,
        OwnRevenueImportRow $normalizedRow,
        Collection $sourceRows,
        Collection $activities,
        Collection $classifications,
    ): void {
        $payload = $normalizedRow->normalized_payload;
        if (! is_array($payload)
            || ! hash_equals($normalizedRow->row_hash, $this->canonicalJson->hash($payload))) {
            throw ValidationException::withMessages([
                'file' => 'Los datos analizados de la Hoja de trabajo ya no son íntegros.',
            ]);
        }

        $activityCode = $payload['activityCode'] ?? null;
        $specificItemCode = $payload['specificItemCode'] ?? null;
        $activity = is_string($activityCode) ? $activities->get($activityCode) : null;
        $classification = is_string($specificItemCode) ? $classifications->get($specificItemCode) : null;
        if (! $activity instanceof OwnRevenueActivity || ! $classification instanceof ExpenseClassification) {
            throw ValidationException::withMessages([
                'file' => 'La actividad o la partida cambió después del análisis; vuelva a analizar la Hoja de trabajo.',
            ]);
        }

        $months = $this->validatedMonths($payload['months'] ?? null);
        $annualAmountCents = $this->amounts->sum($months);
        if ($annualAmountCents === null) {
            throw ValidationException::withMessages(['file' => 'La suma anual excede el importe máximo permitido.']);
        }
        $line = OwnRevenueWorkSheetLine::query()->create([
            'own_revenue_budget_id' => $budget->id,
            'own_revenue_import_file_id' => $file->id,
            'own_revenue_activity_id' => $activity->id,
            'expense_classification_id' => $classification->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'item_name' => is_string($payload['itemName'] ?? null) ? $payload['itemName'] : '',
            'specific_item_code' => $classification->specific_item_code,
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'annual_amount_cents' => $annualAmountCents,
            'sort_order' => $normalizedRow->row_number,
        ]);

        $originRows = $this->validatedOriginRows($payload['sourceRows'] ?? null, $sourceRows);
        $this->createOrigins($line, $originRows, null);
        foreach ($months as $month => $amountCents) {
            $monthRecord = $line->months()->create([
                'month' => $month,
                'amount_cents' => $amountCents,
            ]);
            $this->createOrigins($monthRecord, $originRows, 'month.'.$month);
        }
    }

    /** @return array<int, string> */
    private function validatedMonths(mixed $months): array
    {
        if (! is_array($months)) {
            throw ValidationException::withMessages(['file' => 'La calendarización analizada no es válida.']);
        }

        $validated = [];
        foreach (range(1, 12) as $month) {
            $amount = $months[$month] ?? $months[(string) $month] ?? null;
            if (! is_string($amount) || ! $this->amounts->isValid($amount)) {
                throw ValidationException::withMessages(['file' => 'La calendarización analizada no es válida.']);
            }
            $validated[$month] = $this->amounts->normalize($amount);
        }

        if (count($months) !== 12) {
            throw ValidationException::withMessages(['file' => 'La calendarización debe contener exactamente doce meses.']);
        }

        return $validated;
    }

    /**
     * @param  Collection<int, OwnRevenueImportRow>  $sourceRows
     * @return list<OwnRevenueImportRow>
     */
    private function validatedOriginRows(mixed $rowNumbers, Collection $sourceRows): array
    {
        if (! is_array($rowNumbers) || $rowNumbers === []) {
            throw ValidationException::withMessages(['file' => 'No se encontró el origen de un renglón analizado.']);
        }

        $origins = [];
        foreach ($rowNumbers as $rowNumber) {
            $sourceRow = is_int($rowNumber) ? $sourceRows->get($rowNumber) : null;
            if (! $sourceRow instanceof OwnRevenueImportRow
                || ! hash_equals($sourceRow->row_hash, $this->canonicalJson->hash($sourceRow->source_payload))) {
                throw ValidationException::withMessages(['file' => 'El origen de los datos analizados ya no es íntegro.']);
            }
            $origins[] = $sourceRow;
        }

        return $origins;
    }

    /** @param list<OwnRevenueImportRow> $sourceRows */
    private function createOrigins(Model $originable, array $sourceRows, ?string $fieldName): void
    {
        foreach ($sourceRows as $sourceRow) {
            $originable->origins()->create([
                'own_revenue_import_row_id' => $sourceRow->id,
                'field_name' => $fieldName,
            ]);
        }
    }
}
