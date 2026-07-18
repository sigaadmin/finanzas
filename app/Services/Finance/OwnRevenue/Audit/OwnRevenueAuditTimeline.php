<?php

namespace App\Services\Finance\OwnRevenue\Audit;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierTransition;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OwnRevenueAuditTimeline
{
    /** @return array{applied_type: ?string, options: list<array{value: string, label: string}>, events: list<array<string, ?string>>} */
    public function forBudget(OwnRevenueBudget $budget, ?string $requestedType): array
    {
        $types = $this->types();
        $appliedType = $requestedType !== null && array_key_exists($requestedType, $types)
            ? $requestedType
            : null;
        $events = collect()
            ->concat($this->configurationEvents($budget))
            ->concat($this->importEvents($budget))
            ->concat($this->planningEvents($budget))
            ->concat($this->exportEvents($budget))
            ->concat($this->modificationEvents($budget))
            ->concat($this->dossierEvents($budget))
            ->concat($this->fuelEvents($budget))
            ->concat($this->closeEvents($budget));

        if ($appliedType !== null) {
            $events = $events->where('type', $appliedType);
        }

        return [
            'applied_type' => $appliedType,
            'options' => collect($types)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))
                ->values()
                ->all(),
            'events' => $events
                ->sortByDesc(fn (array $event): string => $event['occurred_at'].'|'.$event['id'])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, string> */
    private function types(): array
    {
        return [
            'configuration' => 'Configuración anual',
            'import' => 'Importaciones',
            'planning_authorization' => 'Planeación y autorización',
            'export' => 'Exportaciones oficiales',
            'modification' => 'Modificaciones presupuestales',
            'expense_dossier' => 'Expedientes',
            'fuel' => 'Combustible',
            'close' => 'Cierre anual',
        ];
    }

    /** @return Collection<int, array<string, ?string>> */
    private function configurationEvents(OwnRevenueBudget $budget): Collection
    {
        $budget->loadMissing(['createdBy:id,name', 'cogConfirmedBy:id,name']);
        $events = collect([
            $this->event(
                'budget:'.$budget->id,
                'configuration',
                $budget->created_at,
                'Ejercicio anual creado',
                "Se abrió la configuración de Ingresos Propios {$budget->fiscal_year} para la región {$budget->region_code}.",
                $budget->createdBy?->name,
                (string) $budget->fiscal_year,
            ),
        ]);

        if ($budget->cog_confirmed_at !== null) {
            $events->push($this->event(
                'cog:'.$budget->id,
                'configuration',
                $budget->cog_confirmed_at,
                'Catálogo COG confirmado',
                'Se confirmó el clasificador del gasto utilizado por el ejercicio.',
                $budget->cogConfirmedBy?->name,
                $budget->cog_source_year === null ? null : (string) $budget->cog_source_year,
            ));
        }

        return $events;
    }

    /** @return Collection<int, array<string, ?string>> */
    private function importEvents(OwnRevenueBudget $budget): Collection
    {
        return $budget->importFiles()
            ->whereNotNull('confirmed_at')
            ->with('confirmedBy:id,name')
            ->get()
            ->map(fn (OwnRevenueImportFile $file): array => $this->event(
                'import:'.$file->id,
                'import',
                $file->confirmed_at,
                'Archivo confirmado',
                'Se incorporó la información revisada de '.$this->formatLabel($file->format).'.',
                $file->confirmedBy?->name,
                $file->original_name,
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function planningEvents(OwnRevenueBudget $budget): Collection
    {
        return $budget->initialBudgets()
            ->with('authorizer:id,name')
            ->get()
            ->map(fn (OwnRevenueInitialBudget $initialBudget): array => $this->event(
                'initial-authorization:'.$initialBudget->id,
                'planning_authorization',
                $initialBudget->authorized_at,
                'Presupuesto inicial autorizado',
                'La propuesta conciliada quedó establecida como presupuesto inicial del ejercicio.',
                $initialBudget->authorizer?->name,
                'Propuesta '.$initialBudget->own_revenue_proposal_id,
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function exportEvents(OwnRevenueBudget $budget): Collection
    {
        return OwnRevenueWorkbookExport::query()
            ->whereHas('initialBudget', fn ($query) => $query
                ->where('own_revenue_budget_id', $budget->id))
            ->with('generator:id,name')
            ->get()
            ->map(fn (OwnRevenueWorkbookExport $export): array => $this->event(
                'export:'.$export->id,
                'export',
                $export->generated_at,
                'Formato oficial generado',
                'Se generó una versión privada del formato '.$this->formatLabel($export->format).'.',
                $export->generator?->name,
                $export->file_name,
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function modificationEvents(OwnRevenueBudget $budget): Collection
    {
        return $budget->budgetModifications()
            ->with(['recordedBy:id,name', 'sourceLine:id,specific_item_code,month', 'destinationLine:id,specific_item_code,month'])
            ->get()
            ->map(fn (OwnRevenueBudgetModification $modification): array => $this->event(
                'modification:'.$modification->id,
                'modification',
                $modification->recorded_at,
                $modification->type === OwnRevenueBudgetModificationType::Transfer
                    ? 'Transferencia presupuestal registrada'
                    : 'Cambio de mes registrado',
                $modification->sourceLine->specific_item_code.' · mes '.$modification->sourceLine->month
                    .' → '.$modification->destinationLine->specific_item_code.' · mes '.$modification->destinationLine->month.'.',
                $modification->recordedBy?->name,
                $modification->reason,
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function dossierEvents(OwnRevenueBudget $budget): Collection
    {
        return OwnRevenueExpenseDossierTransition::query()
            ->whereHas('dossier', fn ($query) => $query
                ->where('own_revenue_budget_id', $budget->id))
            ->with(['actor:id,name', 'dossier:id,folio'])
            ->get()
            ->map(fn (OwnRevenueExpenseDossierTransition $transition): array => $this->event(
                'dossier-transition:'.$transition->id,
                'expense_dossier',
                $transition->occurred_at,
                'Expediente cambió de etapa',
                'El expediente pasó a '.$this->dossierStatusLabel($transition->to_status).'.',
                $transition->actor?->name,
                $transition->dossier->folio,
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function fuelEvents(OwnRevenueBudget $budget): Collection
    {
        $fund = $budget->fuelFund()
            ->with(['openedBy:id,name', 'commissions.creator:id,name', 'commissions.confirmer:id,name'])
            ->first();
        if ($fund === null) {
            return collect();
        }

        $events = collect([
            $this->event(
                'fuel-fund:'.$fund->id,
                'fuel',
                $fund->opened_at,
                'Fondo operativo de combustible abierto',
                'Se registró el valor realmente adquirido para operar las comisiones.',
                $fund->openedBy?->name,
                null,
            ),
        ]);
        $fund->commissions->each(function (OwnRevenueFuelCommission $commission) use ($events): void {
            $events->push($this->event(
                'fuel-commission:'.$commission->id,
                'fuel',
                $commission->created_at,
                'Comisión de combustible registrada',
                $commission->route_description,
                $commission->creator?->name,
                $commission->commission_date?->toDateString(),
            ));
            if ($commission->confirmed_at !== null) {
                $events->push($this->event(
                    'fuel-confirmation:'.$commission->id,
                    'fuel',
                    $commission->confirmed_at,
                    'Consumo de combustible confirmado',
                    'La comisión quedó aplicada al saldo del fondo operativo.',
                    $commission->confirmer?->name,
                    $commission->commission_date?->toDateString(),
                ));
            }
        });

        return $events;
    }

    /** @return Collection<int, array<string, ?string>> */
    private function closeEvents(OwnRevenueBudget $budget): Collection
    {
        $closure = $budget->annualClosure()->with('closedBy:id,name')->first();
        if ($closure === null) {
            return collect();
        }

        return collect([$this->event(
            'close:'.$closure->id,
            'close',
            $closure->closed_at,
            'Ejercicio cerrado definitivamente',
            $closure->note,
            $closure->closedBy?->name,
            substr($closure->fingerprint, 0, 12),
        )]);
    }

    /** @return array<string, ?string> */
    private function event(
        string $id,
        string $type,
        ?CarbonInterface $occurredAt,
        string $title,
        string $description,
        ?string $actorName,
        ?string $reference,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'occurred_at' => $occurredAt?->toISOString(),
            'title' => $title,
            'description' => $description,
            'actor_name' => $actorName,
            'reference' => $reference,
        ];
    }

    private function formatLabel(OwnRevenueImportFormat|string|null $format): string
    {
        $value = $format instanceof OwnRevenueImportFormat ? $format->value : $format;

        return match ($value) {
            'abpre' => 'Justificación de partidas',
            'work_sheet' => 'Hoja de trabajo',
            'technical_sheet' => 'Ficha técnica',
            'fuel' => 'Combustible',
            'travel_expenses' => 'Viáticos',
            default => 'Formato del ejercicio',
        };
    }

    private function dossierStatusLabel(OwnRevenueExpenseDossierStatus $status): string
    {
        return match ($status) {
            OwnRevenueExpenseDossierStatus::Draft => 'Borrador',
            OwnRevenueExpenseDossierStatus::SufficiencyRequested => 'Suficiencia solicitada',
            OwnRevenueExpenseDossierStatus::SufficiencyConfirmed => 'Suficiencia confirmada',
            OwnRevenueExpenseDossierStatus::PurchaseInProgress => 'Compra en proceso',
            OwnRevenueExpenseDossierStatus::PaymentRequested => 'Pago solicitado',
            OwnRevenueExpenseDossierStatus::FinanceAuthorized => 'Autorizado por Finanzas',
            OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized => 'Autorizado por Presupuesto',
            OwnRevenueExpenseDossierStatus::Paid => 'Pagado',
            OwnRevenueExpenseDossierStatus::Rejected => 'Rechazado',
            OwnRevenueExpenseDossierStatus::Cancelled => 'Cancelado',
        };
    }
}
