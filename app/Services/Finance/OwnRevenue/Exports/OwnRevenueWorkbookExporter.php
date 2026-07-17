<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnRevenueWorkbookExporter
{
    public function __construct(
        private readonly AbpreWorkbookExporter $abpre,
        private readonly WorkSheetWorkbookExporter $workSheet,
        private readonly TechnicalSheetWorkbookExporter $technicalSheet,
        private readonly FuelWorkbookExporter $fuel,
        private readonly TravelExpensesWorkbookExporter $travelExpenses,
    ) {}

    public function export(OwnRevenueInitialBudget $initialBudget, User $user, string $format): OwnRevenueWorkbookExport
    {
        $contents = match ($format) {
            'abpre' => $this->abpre->export($this->snapshot($initialBudget)),
            'work_sheet' => $this->workSheet->export($this->snapshot($initialBudget)),
            'technical_sheet' => $this->technicalSheet->export($this->snapshot($initialBudget)),
            'fuel' => $this->fuel->export($this->snapshot($initialBudget)),
            'travel_expenses' => $this->travelExpenses->export($this->snapshot($initialBudget)),
        };

        return $this->store($initialBudget, $user, $format, $contents);
    }

    public function exportAbpre(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        return $this->store($initialBudget, $user, 'abpre', $this->abpre->export($this->snapshot($initialBudget)));
    }

    public function exportWorkSheet(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        return $this->store($initialBudget, $user, 'work_sheet', $this->workSheet->export($this->snapshot($initialBudget)));
    }

    public function exportTechnicalSheet(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        return $this->store($initialBudget, $user, 'technical_sheet', $this->technicalSheet->export($this->snapshot($initialBudget)));
    }

    public function exportFuel(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        return $this->store($initialBudget, $user, 'fuel', $this->fuel->export($this->snapshot($initialBudget)));
    }

    public function exportTravelExpenses(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        return $this->store($initialBudget, $user, 'travel_expenses', $this->travelExpenses->export($this->snapshot($initialBudget)));
    }

    private function snapshot(OwnRevenueInitialBudget $initialBudget): array
    {
        $snapshot = $initialBudget->snapshot;
        $budget = $initialBudget->budget;
        $snapshot['budget'] = array_merge([
            'fiscal_year' => $budget->fiscal_year,
            'institution_name' => $budget->institution_name,
            'responsible_unit_code' => $budget->responsible_unit_code,
            'responsible_unit_name' => $budget->responsible_unit_name,
            'budget_program_code' => $budget->budget_program_code,
            'budget_program_name' => $budget->budget_program_name,
            'component_code' => $budget->component_code,
            'component_name' => $budget->component_name,
            'official_activity_code' => $budget->official_activity_code,
            'official_activity_name' => $budget->official_activity_name,
        ], $snapshot['budget'] ?? [], [
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
        ]);
        $proposal = $initialBudget->proposal()->with(['technicalNeeds.activity', 'fuelNeeds.activity', 'travelCommissions.activity', 'travelCommissions.participants'])->first();

        if ($proposal !== null) {
            $technical = $proposal->technicalNeeds->keyBy('stable_key');
            $snapshot['technical_needs'] = collect($snapshot['technical_needs'] ?? [])->map(function (array $row) use ($technical): array {
                $need = $technical->get($row['stable_key'] ?? '');
                if ($need === null) {
                    return $row;
                }

                return array_merge([
                    'activity_name' => $need->activity->name, 'item_name' => $need->specific_item_name,
                    'chapter_code' => $need->chapter_code, 'chapter_name' => $need->chapter_name,
                    'sequence' => $need->sequence, 'quantity' => $need->quantity, 'unit' => $need->unit,
                    'description' => $need->description,
                    'unit_price_cents' => (string) $need->getRawOriginal('unit_price_cents'),
                    'reference_amount_cents' => (string) $need->getRawOriginal('reference_amount_cents'),
                    'impact_on_goals' => $need->impact_on_goals,
                    'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
                ], $row);
            })->all();

            $fuel = $proposal->fuelNeeds->keyBy('stable_key');
            $snapshot['fuel_needs'] = collect($snapshot['fuel_needs'] ?? [])->map(function (array $row) use ($fuel): array {
                $need = $fuel->get($row['stable_key'] ?? '');
                if ($need === null) {
                    return $row;
                }

                return array_merge($need->only([
                    'commission_date_label', 'operational_month', 'reason', 'vehicle_model', 'kilometers_per_liter',
                    'outbound_origin', 'outbound_destination', 'outbound_kilometers', 'return_origin',
                    'return_destination', 'return_kilometers', 'additional_kilometers', 'total_kilometers',
                    'liters', 'fuel_price', 'override_justification',
                ]), [
                    'activity_name' => $need->activity->name,
                    'mathematical_amount_cents' => (string) $need->getRawOriginal('mathematical_amount_cents'),
                    'rounded_amount_cents' => (string) $need->getRawOriginal('rounded_amount_cents'),
                    'rounding_difference_cents' => (string) $need->getRawOriginal('rounding_difference_cents'),
                ], $row, ['month' => 4]);
            })->all();

            $commissions = $proposal->travelCommissions->keyBy('stable_key');
            $snapshot['travel_commissions'] = collect($snapshot['travel_commissions'] ?? [])->map(function (array $row) use ($commissions): array {
                $commission = $commissions->get($row['stable_key'] ?? '');
                if ($commission === null) {
                    return $row;
                }
                $participants = $commission->participants->keyBy('stable_key');
                $rowParticipants = collect($row['participants'] ?? [])->map(function (array $participantRow) use ($participants): array {
                    $participant = $participants->get($participantRow['stable_key'] ?? '');
                    if ($participant === null) {
                        return $participantRow;
                    }

                    return array_merge($participant->only([
                        'person_name', 'position', 'commission_days', 'per_diem_uma', 'lodging_uma',
                    ]), [
                        'per_diem_amount_cents' => (string) $participant->getRawOriginal('per_diem_amount_cents'),
                        'lodging_amount_cents' => (string) $participant->getRawOriginal('lodging_amount_cents'),
                    ], $participantRow);
                })->all();

                return array_merge($commission->only([
                    'commission_date_label', 'operational_month', 'reason', 'destination', 'food_zone',
                    'lodging_zone', 'uma_value', 'override_justification',
                ]), ['activity_name' => $commission->activity->name], $row, ['participants' => $rowParticipants]);
            })->all();
        }

        return $snapshot;
    }

    private function store(OwnRevenueInitialBudget $initialBudget, User $user, string $format, string $contents): OwnRevenueWorkbookExport
    {
        $fileName = $format.'-'.$initialBudget->budget->fiscal_year.'-'.Str::uuid().'.xlsx';
        $path = 'own-revenue/exports/'.$fileName;
        Storage::disk('local')->put($path, $contents);

        return OwnRevenueWorkbookExport::query()->create([
            'own_revenue_initial_budget_id' => $initialBudget->id,
            'format' => $format, 'storage_disk' => 'local', 'storage_path' => $path,
            'file_name' => $fileName, 'sha256' => hash('sha256', $contents),
            'total_amount_cents' => $initialBudget->getRawOriginal('total_amount_cents'),
            'generated_by' => $user->id, 'generated_at' => now(),
        ]);
    }
}
