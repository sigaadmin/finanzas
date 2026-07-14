<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\ConfirmOwnRevenueAbpreImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\ConfirmOwnRevenueAbpreImportRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueAbpreConfirmationController extends Controller
{
    public function __invoke(
        ConfirmOwnRevenueAbpreImportRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        ConfirmOwnRevenueAbpreImport $confirmImport,
    ): RedirectResponse {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
        $decisions = collect($request->validated('decisions'))
            ->map(fn (array $decision): array => [
                ...$decision,
                'justification' => $decision['justification'] ?? null,
            ])
            ->all();
        $confirmImport->handle($importFile, $request->user(), $decisions);

        Inertia::flash('success', 'Importación ABPRE confirmada correctamente.');

        return to_route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $importFile,
        ]);
    }
}
