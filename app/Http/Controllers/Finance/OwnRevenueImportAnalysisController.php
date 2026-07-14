<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueImportAnalysisController extends Controller
{
    public function __invoke(
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        AnalyzeOwnRevenueImportFile $analyzeFile,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        $result = $analyzeFile->handle($importFile, request()->user());

        if ($result->status === OwnRevenueImportFileStatus::Failed) {
            Inertia::flash('error', 'No fue posible analizar el archivo. Revisa las incidencias e inténtalo nuevamente.');
        } else {
            Inertia::flash('success', 'Archivo analizado correctamente.');
        }

        return to_route('finance.own-revenue.budgets.imports.show', $budget);
    }
}
