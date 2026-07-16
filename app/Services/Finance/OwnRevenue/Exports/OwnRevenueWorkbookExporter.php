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
        $snapshot['budget']['region_code'] = '02-001';
        $snapshot['budget']['region_name'] = 'Felipe Carrillo Puerto';

        return $snapshot;
    }

    private function store(OwnRevenueInitialBudget $initialBudget, User $user, string $format, string $contents): OwnRevenueWorkbookExport
    {
        $fileName = $format.'-'.$initialBudget->budget->fiscal_year.'-'.Str::uuid().'.xlsx';
        $path = 'private/own-revenue/exports/'.$fileName;
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
