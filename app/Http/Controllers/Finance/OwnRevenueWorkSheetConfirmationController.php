<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\ConfirmOwnRevenueWorkSheetImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\ConfirmOwnRevenueWorkSheetImportRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueWorkSheetConfirmationController extends Controller
{
    public function __invoke(
        ConfirmOwnRevenueWorkSheetImportRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        ConfirmOwnRevenueWorkSheetImport $confirmImport,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        $confirmImport->handle(
            $importFile,
            $request->user(),
            $request->validated('analysis_revision'),
        );

        Inertia::flash('success', 'Hoja de trabajo confirmada correctamente.');

        return to_route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $importFile,
        ]);
    }
}
