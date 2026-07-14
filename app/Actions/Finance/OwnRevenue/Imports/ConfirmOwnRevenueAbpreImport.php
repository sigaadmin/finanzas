<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConfirmOwnRevenueAbpreImport
{
    private const REQUIRED_WARNING_DECISIONS = [
        'year.mismatch',
        'region.normalized',
        'abpre.annual_mismatch',
        'abpre.missing_justification',
    ];

    /** @param array<int, array{issue_id:int,resolution:string,resolved_value:mixed,justification:?string}> $decisions */
    public function handle(OwnRevenueImportFile $file, User $user, array $decisions): OwnRevenueImportFile
    {
        Gate::forUser($user)->authorize('confirmImports', $file->budget);

        return DB::transaction(function () use ($file, $user, $decisions): OwnRevenueImportFile {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->status === OwnRevenueImportFileStatus::Confirmed) {
                return $lockedFile;
            }

            $this->validateFile($lockedFile, $budget);
            $this->validateAndStoreDecisions($lockedFile, $user, $decisions);
            $normalizedLines = $lockedFile->rows()->where('row_kind', 'abpre_line')->orderBy('row_number')->get();

            if ($normalizedLines->isEmpty()) {
                throw ValidationException::withMessages(['file' => 'El análisis no contiene líneas ABPRE normalizadas.']);
            }

            foreach ($normalizedLines as $normalizedRow) {
                $payload = $normalizedRow->normalized_payload;
                $classification = ExpenseClassification::query()
                    ->where('fiscal_year', $budget->fiscal_year)
                    ->where('specific_item_code', $payload['specificItemCode'])
                    ->firstOrFail();
                $line = OwnRevenueAbpreLine::query()->create([
                    'own_revenue_budget_id' => $budget->id,
                    'own_revenue_import_file_id' => $lockedFile->id,
                    'expense_classification_id' => $classification->id,
                    'responsible_unit_code' => $payload['responsibleUnitCode'],
                    'responsible_unit_name' => $payload['responsibleUnitName'],
                    'budget_program_code' => $payload['budgetProgramCode'],
                    'budget_program_name' => $payload['budgetProgramName'],
                    'component_code' => $payload['componentCode'],
                    'component_name' => $payload['componentName'],
                    'official_activity_code' => $payload['officialActivityCode'],
                    'official_activity_name' => $payload['officialActivityName'],
                    'region_code' => $payload['regionCode'],
                    'region_name' => $payload['regionName'],
                    'specific_expense_concept_code' => $payload['specificExpenseConceptCode'],
                    'specific_item_code' => $payload['specificItemCode'],
                    'annual_amount_cents' => $payload['annualAmountCents'],
                    'sort_order' => $normalizedRow->row_number,
                ]);

                foreach ($payload['months'] as $month => $amountCents) {
                    $monthly = $line->months()->create(['month' => (int) $month, 'amount_cents' => $amountCents]);
                    $this->createOrigins($lockedFile, $monthly, $payload['sourceRows'], 'month.'.(int) $month);
                }

                $this->createOrigins($lockedFile, $line, $payload['sourceRows'], null);
            }

            $firstLine = $normalizedLines->first()->normalized_payload;
            foreach ($lockedFile->rows()->where('row_kind', 'abpre_justification')->orderBy('row_number')->get() as $row) {
                $payload = $row->normalized_payload;
                $record = OwnRevenueAbpreJustification::query()->create([
                    'own_revenue_budget_id' => $budget->id,
                    'own_revenue_import_file_id' => $lockedFile->id,
                    'chapter_code' => $payload['chapterCode'],
                    'chapter_name' => $payload['chapterName'],
                    'specific_item_code' => $payload['specificItemCode'],
                    'specific_item_name' => $payload['specificItemName'],
                    'budget_program_code' => $payload['budgetProgramCode'],
                    'budget_program_name' => $firstLine['budgetProgramName'],
                    'component_code' => $firstLine['componentCode'],
                    'component_name' => $payload['componentName'],
                    'goals_impact' => $payload['goalsImpact'],
                    'justification' => $payload['justification'],
                    'sort_order' => $row->row_number,
                ]);
                $source = $lockedFile->rows()
                    ->where('row_kind', 'justification')
                    ->where('row_number', $payload['sourceRow'])
                    ->first();
                if ($source !== null) {
                    $record->origins()->create(['own_revenue_import_row_id' => $source->id, 'field_name' => null]);
                }
            }

            OwnRevenueImportFile::query()
                ->where('own_revenue_budget_id', $budget->id)
                ->where('format', OwnRevenueImportFormat::Abpre)
                ->where('status', OwnRevenueImportFileStatus::Confirmed)
                ->whereKeyNot($lockedFile->id)
                ->update([
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

    private function validateFile(OwnRevenueImportFile $file, OwnRevenueBudget $budget): void
    {
        if ($file->format !== OwnRevenueImportFormat::Abpre || $file->status !== OwnRevenueImportFileStatus::Ready) {
            throw ValidationException::withMessages(['file' => 'Sólo puede confirmarse un ABPRE listo.']);
        }

        if ($file->budget_updated_at_at_analysis === null || ! $budget->updated_at->equalTo($file->budget_updated_at_at_analysis)) {
            throw ValidationException::withMessages(['file' => 'El presupuesto cambió después del análisis; vuelva a analizar.']);
        }

        $path = Storage::disk($file->storage_disk)->path($file->storage_path);
        if (hash_file('sha256', $path) !== $file->sha256) {
            throw ValidationException::withMessages(['file' => 'La huella del archivo almacenado no coincide.']);
        }

        if ($file->issues()->where('severity', OwnRevenueImportIssueSeverity::Error)->exists()) {
            throw ValidationException::withMessages(['file' => 'El análisis todavía contiene errores.']);
        }
    }

    /** @param array<int, array{issue_id:int,resolution:string,resolved_value:mixed,justification:?string}> $decisions */
    private function validateAndStoreDecisions(OwnRevenueImportFile $file, User $user, array $decisions): void
    {
        $decisionsByIssue = collect($decisions)->keyBy('issue_id');
        $requiredIssues = $file->issues()->whereIn('code', self::REQUIRED_WARNING_DECISIONS)->get();

        foreach ($requiredIssues as $issue) {
            if (! $decisionsByIssue->has($issue->id)) {
                throw ValidationException::withMessages(['decisions' => 'Debe aceptar o resolver todas las advertencias requeridas.']);
            }
        }

        foreach ($decisions as $decision) {
            $issue = OwnRevenueImportIssue::query()
                ->where('own_revenue_import_file_id', $file->id)
                ->findOrFail($decision['issue_id']);
            if (! in_array($decision['resolution'], ['manual', 'xlsx', 'custom'], true)) {
                throw ValidationException::withMessages(['decisions' => 'La resolución seleccionada no es válida.']);
            }
            $issue->decisions()->create([
                'own_revenue_import_row_id' => $issue->own_revenue_import_row_id,
                'current_value' => $issue->context,
                'proposed_value' => null,
                'resolved_value' => $decision['resolved_value'],
                'resolution' => $decision['resolution'],
                'justification' => $decision['justification'],
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
        }
    }

    /** @param list<int> $sourceRows */
    private function createOrigins(OwnRevenueImportFile $file, Model $originable, array $sourceRows, ?string $field): void
    {
        foreach ($sourceRows as $rowNumber) {
            $source = $file->rows()->where('row_kind', 'budget_line')->where('row_number', $rowNumber)->first();
            if ($source !== null) {
                $originable->origins()->create(['own_revenue_import_row_id' => $source->id, 'field_name' => $field]);
            }
        }
    }
}
