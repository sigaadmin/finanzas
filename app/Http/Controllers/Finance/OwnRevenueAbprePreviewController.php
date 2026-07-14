<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueImportViewData;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueAbprePreviewController extends Controller
{
    public function __construct(private readonly OwnRevenueImportViewData $viewData) {}

    public function __invoke(OwnRevenueBudget $budget, OwnRevenueImportFile $importFile): Response
    {
        Gate::authorize('viewImports', $budget);
        abort_unless($importFile->format === OwnRevenueImportFormat::Abpre, 404);
        $importFile->loadCount($this->viewData->issueCounts());

        return Inertia::render('finance/own-revenue/imports/preview', [
            'budget' => $this->viewData->budget($budget),
            'selected_file' => $this->viewData->file($importFile),
            'preview' => $this->viewData->preview($importFile),
            'decision_warnings' => $this->viewData->decisionWarnings($importFile),
            'permissions' => [
                'upload' => Gate::allows('manageImports', $budget),
                'manage' => Gate::allows('manageImports', $budget),
                'confirm' => Gate::allows('confirmImports', $budget),
                'download' => Gate::allows('viewImports', $budget),
            ],
        ]);
    }
}
