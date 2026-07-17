<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Exports\GenerateOwnRevenueWorkbookExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Exports\GenerateOwnRevenueWorkbookExportRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnRevenueWorkbookExportController extends Controller
{
    public function store(
        GenerateOwnRevenueWorkbookExportRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueInitialBudget $initialBudget,
        GenerateOwnRevenueWorkbookExport $generate,
    ): RedirectResponse {
        $generate->handle($budget, $initialBudget, $request->user(), $request->validated('format'));

        return to_route('finance.own-revenue.budgets.planning.show', $budget)
            ->with('success', 'El archivo quedó generado y está listo para descargar.');
    }

    public function download(OwnRevenueWorkbookExport $workbookExport): StreamedResponse
    {
        Gate::authorize('view', $workbookExport->initialBudget->budget);

        $disk = Storage::disk($workbookExport->storage_disk);
        abort_unless($disk->exists($workbookExport->storage_path), 404);

        return $disk->download(
            $workbookExport->storage_path,
            $workbookExport->file_name,
        );
    }
}
