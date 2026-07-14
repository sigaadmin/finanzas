<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFileRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueImportAnalysisController extends Controller
{
    public function __invoke(
        AnalyzeOwnRevenueImportFileRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        AnalyzeOwnRevenueImportFile $analyzeFile,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        $result = $analyzeFile->handle($importFile, $request->user());

        if ($result->status === OwnRevenueImportFileStatus::Failed) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No fue posible analizar el archivo. Revisa las incidencias e inténtalo nuevamente.',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Archivo analizado correctamente.',
            ]);
        }

        if ($request->boolean('return_to_preview')) {
            return to_route('finance.own-revenue.budgets.imports.files.preview', [
                'budget' => $budget,
                'importFile' => $importFile,
            ]);
        }

        return to_route('finance.own-revenue.budgets.imports.show', $budget);
    }
}
