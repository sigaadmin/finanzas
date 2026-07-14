<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\StoreOwnRevenueImportDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\StoreOwnRevenueImportDecisionRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueImportDecisionController extends Controller
{
    public function __invoke(
        StoreOwnRevenueImportDecisionRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        OwnRevenueImportIssue $issue,
        StoreOwnRevenueImportDecision $storeDecision,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        abort_unless($issue->own_revenue_import_file_id === $importFile->id, 404);
        $storeDecision->handle(
            $importFile,
            $issue,
            $request->user(),
            $request->validated('analysis_revision'),
            $request->validated('decision'),
            $request->validated('justification'),
        );

        Inertia::flash('success', 'Decisión guardada correctamente.');

        return back();
    }
}
