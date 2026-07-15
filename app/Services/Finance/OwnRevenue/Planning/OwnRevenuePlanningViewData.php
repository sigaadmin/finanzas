<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenuePlanningCorrection;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class OwnRevenuePlanningViewData
{
    private const PER_PAGE = 25;

    private const SECTIONS = ['technical', 'fuel', 'travel'];

    public function __construct(private readonly OwnRevenueProposalReadiness $readiness) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget, Request $request): array
    {
        $section = in_array($request->string('section')->toString(), self::SECTIONS, true)
            ? $request->string('section')->toString()
            : 'technical';
        $proposal = $this->selectedProposal($budget, $request->integer('proposal_version'));
        $readiness = $this->readiness->forBudget($budget);

        return [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'uma_value' => $budget->uma_value === null ? null : (string) $budget->uma_value,
                'fuel_price_per_liter' => $budget->fuel_price_per_liter === null ? null : (string) $budget->fuel_price_per_liter,
                'fuel_budget_month' => $budget->fuel_budget_month,
            ],
            'readiness' => [
                'ready' => $readiness->ready,
                'blockers' => $readiness->blockers,
                'source_file_ids' => $readiness->fileIds,
                'source_fingerprint' => $readiness->fingerprint,
            ],
            'proposal' => $proposal === null ? null : $this->proposal($proposal),
            'versions' => $this->versions($budget),
            'section' => $section,
            'summaries' => $proposal === null ? $this->emptySummaries() : $this->summaries($proposal),
            'rows' => $proposal === null ? $this->emptyRows() : $this->rows($proposal, $section),
            'selected_detail' => $proposal === null ? null : $this->selectedDetail($proposal, $section, $request->integer('detail_id')),
            'catalogs' => $this->catalogs($budget),
            'permissions' => [
                'create' => Gate::allows('createProposal', $budget),
                'edit' => Gate::allows('editProposal', $budget)
                    && ($proposal === null || $proposal->status === OwnRevenueProposalStatus::Draft),
            ],
        ];
    }

    private function selectedProposal(OwnRevenueBudget $budget, int $version): ?OwnRevenueProposal
    {
        $query = $budget->proposals()->with([
            'sourceAbpreFile:id,original_name,version_number',
            'sourceWorkSheetFile:id,original_name,version_number',
            'sourceTechnicalSheetFile:id,original_name,version_number',
            'sourceFuelFile:id,original_name,version_number',
            'sourceTravelExpensesFile:id,original_name,version_number',
        ]);

        return $version > 0
            ? $query->where('version_number', $version)->first()
            : $query->orderByDesc('version_number')->orderByDesc('id')->first();
    }

    /** @return array<string, mixed> */
    private function proposal(OwnRevenueProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'version_number' => $proposal->version_number,
            'status' => $proposal->status->value,
            'total_amount_cents' => (string) $proposal->getRawOriginal('total_amount_cents'),
            'created_at' => $proposal->created_at?->toISOString(),
            'calculated_at' => $proposal->calculated_at?->toISOString(),
            'sources' => [
                'ABPRE' => $this->sourceLabel($proposal->sourceAbpreFile),
                'Hoja de trabajo' => $this->sourceLabel($proposal->sourceWorkSheetFile),
                'Ficha técnica' => $this->sourceLabel($proposal->sourceTechnicalSheetFile),
                'Combustible' => $this->sourceLabel($proposal->sourceFuelFile),
                'Viáticos' => $this->sourceLabel($proposal->sourceTravelExpensesFile),
            ],
        ];
    }

    private function sourceLabel(?Model $file): ?string
    {
        if ($file === null) {
            return null;
        }

        return $file->original_name.' · versión '.$file->version_number;
    }

    /** @return list<array<string, mixed>> */
    private function versions(OwnRevenueBudget $budget): array
    {
        return $budget->proposals()->orderByDesc('version_number')->orderByDesc('id')->get()
            ->map(fn (OwnRevenueProposal $proposal): array => [
                'id' => $proposal->id,
                'version_number' => $proposal->version_number,
                'status' => $proposal->status->value,
                'total_amount_cents' => (string) $proposal->getRawOriginal('total_amount_cents'),
                'created_at' => $proposal->created_at?->toISOString(),
            ])->all();
    }

    /** @return array<string, array{count: int, total_amount_cents: string}> */
    private function summaries(OwnRevenueProposal $proposal): array
    {
        return [
            'technical' => ['count' => $proposal->technicalNeeds()->count(), 'total_amount_cents' => (string) $proposal->technicalNeeds()->sum('budget_amount_cents')],
            'fuel' => ['count' => $proposal->fuelNeeds()->count(), 'total_amount_cents' => (string) $proposal->fuelNeeds()->sum('budget_amount_cents')],
            'travel' => ['count' => $proposal->travelCommissions()->count(), 'total_amount_cents' => (string) $proposal->travelCommissions()->sum('total_amount_cents')],
        ];
    }

    /** @return array<string, array{count: int, total_amount_cents: string}> */
    private function emptySummaries(): array
    {
        return [
            'technical' => ['count' => 0, 'total_amount_cents' => '0'],
            'fuel' => ['count' => 0, 'total_amount_cents' => '0'],
            'travel' => ['count' => 0, 'total_amount_cents' => '0'],
        ];
    }

    /** @return array<string, mixed> */
    private function rows(OwnRevenueProposal $proposal, string $section): array
    {
        $query = match ($section) {
            'fuel' => $proposal->fuelNeeds()->with('activity:id,code,name')->withExists('corrections')->orderBy('sort_order')->orderBy('id'),
            'travel' => $proposal->travelCommissions()->with('activity:id,code,name')->withCount('participants')->withExists('corrections')->orderBy('sort_order')->orderBy('id'),
            default => $proposal->technicalNeeds()->with('activity:id,code,name')->withExists('corrections')->orderBy('sort_order')->orderBy('id'),
        };
        $paginator = $query->paginate(self::PER_PAGE, ['*'], 'page');
        $paginator->setCollection($paginator->getCollection()->map(fn (Model $row): array => $this->row($row, $section)));

        return $paginator->toArray();
    }

    /** @return array<string, mixed> */
    private function row(Model $row, string $section): array
    {
        $common = [
            'id' => $row->id,
            'activity' => ['id' => $row->activity->id, 'code' => $row->activity->code, 'name' => $row->activity->name],
            'sort_order' => $row->sort_order,
            'source_label' => $this->rowSourceLabel($row, $section),
            'has_corrections' => (bool) $row->corrections_exists,
        ];

        return match ($section) {
            'fuel' => [...$common,
                'title' => $row->outbound_origin.' → '.$row->outbound_destination,
                'reason' => $row->reason,
                'operational_month' => $row->operational_month,
                'budget_month' => $row->budget_month,
                'total_kilometers' => (string) $row->total_kilometers,
                'budget_amount_cents' => (string) $row->getRawOriginal('budget_amount_cents'),
            ],
            'travel' => [...$common,
                'title' => $row->destination,
                'reason' => $row->reason,
                'operational_month' => $row->operational_month,
                'budget_month' => $row->budget_month,
                'participants_count' => $row->participants_count,
                'total_amount_cents' => (string) $row->getRawOriginal('total_amount_cents'),
            ],
            default => [...$common,
                'title' => $row->description,
                'description' => $row->description,
                'specific_item_code' => $row->specific_item_code,
                'specific_item_name' => $row->specific_item_name,
                'quantity' => (string) $row->quantity,
                'unit' => $row->unit,
                'budget_month' => $row->budget_month,
                'budget_amount_cents' => (string) $row->getRawOriginal('budget_amount_cents'),
            ],
        };
    }

    private function rowSourceLabel(Model $row, string $section): string
    {
        $sourceId = match ($section) {
            'fuel' => $row->source_fuel_plan_id,
            'travel' => $row->source_travel_commission_id,
            default => $row->source_technical_sheet_need_id,
        };

        return $sourceId === null ? 'Capturado en Planeación' : 'Importado y editable';
    }

    /** @return array<string, mixed>|null */
    private function selectedDetail(OwnRevenueProposal $proposal, string $section, int $detailId): ?array
    {
        if ($detailId < 1) {
            return null;
        }
        $row = match ($section) {
            'fuel' => $proposal->fuelNeeds()->find($detailId),
            'travel' => $proposal->travelCommissions()->find($detailId),
            default => $proposal->technicalNeeds()->find($detailId),
        };
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'title' => match ($section) {
                'fuel' => $row->outbound_origin.' → '.$row->outbound_destination,
                'travel' => $row->destination,
                default => $row->description,
            },
            'corrections' => $row->corrections()->with('actor:id,name')->orderByDesc('corrected_at')->get()
                ->map(fn (OwnRevenuePlanningCorrection $correction): array => [
                    'id' => $correction->id,
                    'field' => $this->fieldLabel($correction->field),
                    'old_value' => $correction->old_value,
                    'new_value' => $correction->new_value,
                    'justification' => $correction->justification,
                    'actor_name' => $correction->actor->name,
                    'corrected_at' => $correction->corrected_at?->toISOString(),
                ])->all(),
        ];
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'budget_amount_cents' => 'Importe definitivo',
            'total_kilometers' => 'Kilómetros totales',
            'route_points' => 'Origen y destino',
            'kilometers_per_liter' => 'Rendimiento del vehículo',
            'fuel_price' => 'Precio del combustible',
            'food_zone' => 'Zona de alimentación',
            'lodging_zone' => 'Zona de hospedaje',
            'uma_value' => 'Valor UMA',
            'per_diem_uma' => 'Tarifa de alimentación',
            'lodging_uma' => 'Tarifa de hospedaje',
            default => 'Dato corregido',
        };
    }

    /** @return array<string, mixed> */
    private function catalogs(OwnRevenueBudget $budget): array
    {
        return [
            'activities' => $budget->activities()->orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name']),
            'expense_classifications' => ExpenseClassification::query()->where('fiscal_year', $budget->fiscal_year)
                ->orderBy('specific_item_code')->get(['id', 'specific_item_code', 'specific_item_name']),
            'routes' => $budget->planningRoutes()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'destinations' => $budget->travelDestinations()->where('is_active', true)->orderBy('destination')->get(),
            'rates' => $budget->travelRates()->where('is_active', true)->orderBy('position')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyRows(): array
    {
        return (new LengthAwarePaginator([], 0, self::PER_PAGE, 1))->toArray();
    }
}
