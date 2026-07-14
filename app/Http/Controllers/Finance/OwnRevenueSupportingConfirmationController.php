<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\ConfirmOwnRevenueSupportingImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\ConfirmOwnRevenueSupportingImportRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueSupportingConfirmationController extends Controller
{
    public function __invoke(
        ConfirmOwnRevenueSupportingImportRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        ConfirmOwnRevenueSupportingImport $confirmImport,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        $confirmImport->handle($importFile, $request->user(), $request->validated('analysis_revision'));

        Inertia::flash('success', 'Archivo confirmado correctamente. La actividad se asignará durante la conciliación.');

        return to_route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $importFile,
        ]);
    }
}
