<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnRevenueWorkbookExporter
{
    public function __construct(private readonly AbpreWorkbookExporter $abpre) {}

    public function exportAbpre(OwnRevenueInitialBudget $initialBudget, User $user): OwnRevenueWorkbookExport
    {
        $snapshot = $initialBudget->snapshot;
        $snapshot['budget']['region_code'] = '02-001';
        $snapshot['budget']['region_name'] = 'Felipe Carrillo Puerto';
        $contents = $this->abpre->export($snapshot);
        $fileName = 'abpre-'.$initialBudget->budget->fiscal_year.'-'.Str::uuid().'.xlsx';
        $path = 'private/own-revenue/exports/'.$fileName;
        Storage::disk('local')->put($path, $contents);

        return OwnRevenueWorkbookExport::query()->create([
            'own_revenue_initial_budget_id' => $initialBudget->id,
            'format' => 'abpre', 'storage_disk' => 'local', 'storage_path' => $path,
            'file_name' => $fileName, 'sha256' => hash('sha256', $contents),
            'total_amount_cents' => $initialBudget->getRawOriginal('total_amount_cents'),
            'generated_by' => $user->id, 'generated_at' => now(),
        ]);
    }
}
