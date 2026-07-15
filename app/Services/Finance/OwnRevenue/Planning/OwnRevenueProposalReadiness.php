<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use Illuminate\Support\Collection;

class OwnRevenueProposalReadiness
{
    /** @var array<string, string> */
    private const LABELS = [
        'abpre' => 'ABPRE',
        'work_sheet' => 'Hoja de trabajo',
        'technical_sheet' => 'Ficha técnica',
        'fuel' => 'Combustible',
        'travel_expenses' => 'Viáticos',
    ];

    public function __construct(private readonly CanonicalJson $canonicalJson) {}

    public function forBudget(OwnRevenueBudget $budget): OwnRevenueProposalReadinessResult
    {
        $files = $this->currentConfirmedFiles($budget);
        $fileIds = collect(OwnRevenueImportFormat::cases())
            ->mapWithKeys(fn (OwnRevenueImportFormat $format): array => [
                $format->value => $files->get($format->value)?->id,
            ])
            ->filter(fn (?int $id): bool => $id !== null)
            ->all();
        $blockers = $this->blockers($budget, $files);

        return new OwnRevenueProposalReadinessResult(
            ready: $blockers === [],
            fileIds: $fileIds,
            fingerprint: $this->canonicalJson->hash($fileIds),
            blockers: $blockers,
        );
    }

    /** @return Collection<string, OwnRevenueImportFile> */
    private function currentConfirmedFiles(OwnRevenueBudget $budget): Collection
    {
        return $budget->importFiles()
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->whereIn('format', OwnRevenueImportFormat::cases())
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (OwnRevenueImportFile $file): string => $file->format->value)
            ->keyBy(fn (OwnRevenueImportFile $file): string => $file->format->value);
    }

    /** @param Collection<string, OwnRevenueImportFile> $files @return list<string> */
    private function blockers(OwnRevenueBudget $budget, Collection $files): array
    {
        $blockers = [];
        foreach (self::LABELS as $format => $label) {
            if (! $files->has($format)) {
                $blockers[] = "Falta confirmar el archivo {$label}.";
            }
        }

        $supportingFormats = [
            OwnRevenueImportFormat::TechnicalSheet,
            OwnRevenueImportFormat::Fuel,
            OwnRevenueImportFormat::TravelExpenses,
        ];
        $supportingFiles = $files->filter(
            fn (OwnRevenueImportFile $file): bool => in_array($file->format, $supportingFormats, true),
        );
        if ($supportingFiles->contains(fn (OwnRevenueImportFile $file): bool => match ($file->format) {
            OwnRevenueImportFormat::TechnicalSheet => $file->technicalSheetNeeds()->whereNull('own_revenue_activity_id')->exists(),
            OwnRevenueImportFormat::Fuel => $file->fuelPlans()->whereNull('own_revenue_activity_id')->exists(),
            OwnRevenueImportFormat::TravelExpenses => $file->travelCommissions()->whereNull('own_revenue_activity_id')->exists(),
            default => false,
        })) {
            $blockers[] = 'Hay registros complementarios sin actividad asignada.';
        }

        if ($files->isNotEmpty() && OwnRevenueImportFile::query()
            ->whereKey($files->modelKeys())
            ->whereHas('issues', fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Error))
            ->exists()) {
            $blockers[] = 'Las importaciones confirmadas todavía contienen incidencias de error.';
        }

        if ($budget->cog_status !== CogCatalogStatus::Confirmed) {
            $blockers[] = 'Confirma el catálogo COG antes de crear la propuesta.';
        }
        if ($budget->uma_status === AnnualValueStatus::PendingReview
            || $budget->fuel_price_status === AnnualValueStatus::PendingReview
            || $budget->uma_value === null
            || $budget->fuel_price_per_liter === null) {
            $blockers[] = 'Revisa y confirma los valores anuales de UMA y combustible.';
        }
        if (collect([
            $budget->institution_name,
            $budget->responsible_unit_code,
            $budget->responsible_unit_name,
            $budget->budget_program_code,
            $budget->budget_program_name,
            $budget->component_code,
            $budget->component_name,
            $budget->official_activity_code,
            $budget->official_activity_name,
        ])->contains(fn (?string $value): bool => blank($value))) {
            $blockers[] = 'Completa los datos institucionales de la planeación.';
        }
        $roles = $budget->signatories()->pluck('role_key');
        if (! $roles->contains('prepared_by') || ! $roles->contains('authorized_by')) {
            $blockers[] = 'Registra las personas que elaboran y autorizan la planeación.';
        }

        return $blockers;
    }
}
