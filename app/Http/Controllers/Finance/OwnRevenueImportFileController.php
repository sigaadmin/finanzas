<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\AssignOwnRevenueImportFormat;
use App\Actions\Finance\OwnRevenue\Imports\DiscardOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\StoreOwnRevenueImportFileRequest;
use App\Http\Requests\Finance\OwnRevenue\Imports\UpdateOwnRevenueImportFileFormatRequest;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnRevenueImportFileController extends Controller
{
    public function store(
        StoreOwnRevenueImportFileRequest $request,
        OwnRevenueBudget $budget,
        StartOwnRevenueImportSession $startSession,
        UploadOwnRevenueImportFile $uploadFile,
    ): RedirectResponse {
        $session = $startSession->handle($budget, $request->user());
        $uploadFile->handle(
            $session,
            $request->user(),
            $request->file('file'),
            $request->boolean('force_reanalysis'),
        );

        Inertia::flash('success', 'Archivo XLSX cargado correctamente.');

        return to_route('finance.own-revenue.budgets.imports.show', $budget);
    }

    public function updateFormat(
        UpdateOwnRevenueImportFileFormatRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        AssignOwnRevenueImportFormat $assignFormat,
    ): RedirectResponse {
        $this->assertOwnership($budget, $importFile);
        $assignFormat->handle(
            $importFile,
            $request->user(),
            OwnRevenueImportFormat::from($request->validated('format')),
        );

        Inertia::flash('success', 'Formato del archivo actualizado correctamente.');

        return to_route('finance.own-revenue.budgets.imports.show', $budget);
    }

    public function download(
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
    ): StreamedResponse {
        $this->assertOwnership($budget, $importFile);
        Gate::authorize('viewImports', $budget);

        return Storage::disk($importFile->storage_disk)->download(
            $importFile->storage_path,
            $importFile->original_name,
        );
    }

    public function destroy(
        OwnRevenueBudget $budget,
        OwnRevenueImportFile $importFile,
        DiscardOwnRevenueImportFile $discardFile,
    ): RedirectResponse {
        $this->assertOwnership($budget, $importFile);
        $discardFile->handle($importFile, request()->user());

        Inertia::flash('success', 'Versión descartada correctamente.');

        return to_route('finance.own-revenue.budgets.imports.show', $budget);
    }

    private function assertOwnership(OwnRevenueBudget $budget, OwnRevenueImportFile $importFile): void
    {
        abort_unless($importFile->own_revenue_budget_id === $budget->id, 404);
    }
}
