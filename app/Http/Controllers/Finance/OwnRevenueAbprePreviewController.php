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
        abort_unless(in_array($importFile->format, [
            OwnRevenueImportFormat::Abpre,
            OwnRevenueImportFormat::WorkSheet,
        ], true), 404);
        $importFile->loadCount($this->viewData->issueCounts());

        $workSheetState = $importFile->format === OwnRevenueImportFormat::WorkSheet
            ? $this->viewData->workSheetViewState($importFile)
            : null;
        $workSheetConfirmation = $importFile->format === OwnRevenueImportFormat::WorkSheet
            ? $this->viewData->workSheetConfirmationState($importFile)
            : null;
        $canManage = Gate::allows('manageImports', $budget);
        $canConfirm = Gate::allows('confirmImports', $budget);
        $previewData = $importFile->format === OwnRevenueImportFormat::WorkSheet
            ? [
                'preview' => $this->viewData->workSheetPreview($importFile),
                'blocking_issues' => $this->viewData->workSheetIssues($importFile, true),
                'review_issues' => $this->viewData->workSheetIssues($importFile, false),
                'view_state' => $workSheetState,
                'decisions_enabled' => $workSheetState === 'ready'
                    && $canManage
                    && $this->viewData->workSheetDecisionsAreCurrent($importFile),
                'can_confirm' => $canManage
                    && $canConfirm
                    && ($workSheetConfirmation['can_confirm'] ?? false),
                'confirm_reasons' => $canManage && $canConfirm
                    ? ($workSheetConfirmation['reasons'] ?? [])
                    : ['Puedes consultar esta revisión, pero no confirmarla.'],
            ]
            : [
                'preview' => $this->viewData->preview($importFile),
                'decision_warnings' => $this->viewData->decisionWarnings($importFile),
            ];

        return Inertia::render('finance/own-revenue/imports/preview', [
            'budget' => $this->viewData->budget($budget),
            'selected_file' => $this->viewData->file($importFile),
            ...$previewData,
            'permissions' => [
                'upload' => $canManage,
                'manage' => $canManage,
                'confirm' => $canConfirm,
                'download' => Gate::allows('viewImports', $budget),
            ],
        ]);
    }
}
