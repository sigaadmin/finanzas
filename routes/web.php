<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\ChargeConceptController;
use App\Http\Controllers\Finance\ChargeConceptOfficialLinkController;
use App\Http\Controllers\Finance\ExpenseClassificationImportController;
use App\Http\Controllers\Finance\OfficialFeeConceptController;
use App\Http\Controllers\Finance\OwnRevenueAbpreConfirmationController;
use App\Http\Controllers\Finance\OwnRevenueAbprePreviewController;
use App\Http\Controllers\Finance\OwnRevenueActivityExceptionController;
use App\Http\Controllers\Finance\OwnRevenueActivityReconciliationController;
use App\Http\Controllers\Finance\OwnRevenueActivityRuleController;
use App\Http\Controllers\Finance\OwnRevenueBudgetController;
use App\Http\Controllers\Finance\OwnRevenueCogConfirmationController;
use App\Http\Controllers\Finance\OwnRevenueImportAnalysisController;
use App\Http\Controllers\Finance\OwnRevenueImportController;
use App\Http\Controllers\Finance\OwnRevenueImportDecisionController;
use App\Http\Controllers\Finance\OwnRevenueImportFileController;
use App\Http\Controllers\Finance\OwnRevenuePlanningController;
use App\Http\Controllers\Finance\OwnRevenueProposalCreationController;
use App\Http\Controllers\Finance\OwnRevenueProposalFuelNeedController;
use App\Http\Controllers\Finance\OwnRevenueProposalTechnicalNeedController;
use App\Http\Controllers\Finance\OwnRevenueProposalTravelCommissionController;
use App\Http\Controllers\Finance\OwnRevenueProposalTravelParticipantController;
use App\Http\Controllers\Finance\OwnRevenueRouteController;
use App\Http\Controllers\Finance\OwnRevenueSupportingConfirmationController;
use App\Http\Controllers\Finance\OwnRevenueTravelDestinationController;
use App\Http\Controllers\Finance\OwnRevenueTravelRateController;
use App\Http\Controllers\Finance\OwnRevenueWorkSheetConfirmationController;
use App\Http\Controllers\Finance\PaymentProcedureController;
use App\Http\Controllers\Finance\PaymentRegistrationController;
use App\Http\Controllers\Finance\ReceiptCancellationController;
use App\Http\Controllers\Finance\ReceiptController;
use App\Http\Controllers\Finance\ReceiptValidationController;
use App\Http\Controllers\Finance\SeqDepositController;
use App\Http\Controllers\Finance\SeqReportController;
use App\Http\Controllers\Finance\SeqReportExportController;
use App\Http\Controllers\Finance\StudentLookupController;
use App\Http\Controllers\Finance\U300BudgetAdjustmentController;
use App\Http\Controllers\Finance\U300BudgetExecutionController;
use App\Http\Controllers\Finance\U300CogConversionController;
use App\Http\Controllers\Finance\U300FederalVerdictController;
use App\Http\Controllers\Finance\U300FinancialReportController;
use App\Http\Controllers\Finance\U300ImportController;
use App\Http\Controllers\Finance\U300ProgramController;
use App\Http\Controllers\Finance\U300TechnicalSheetController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::prefix('finance')->name('finance.')->group(function () {
    Route::get('receipts/validate/{token}', ReceiptValidationController::class)
        ->name('receipts.validate');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('can:operate-finance')
        ->name('dashboard');

    Route::prefix('finance')->name('finance.')->middleware('can:operate-finance')->group(function () {
        Route::get('students/search', StudentLookupController::class)
            ->name('students.search');

        Route::resource('payment-procedures', PaymentProcedureController::class)
            ->only(['index', 'create', 'store', 'show', 'update']);

        Route::post('payment-procedures/{payment_procedure}/payments', [PaymentRegistrationController::class, 'store'])
            ->name('payment-procedures.payments.store');

        Route::get('receipts', [ReceiptController::class, 'index'])
            ->name('receipts.index');

        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])
            ->name('receipts.show');

        Route::get('receipts/{receipt}/print', [ReceiptController::class, 'print'])
            ->name('receipts.print');

        Route::post('receipts/{receipt}/cancel', ReceiptCancellationController::class)
            ->name('receipts.cancel');

        Route::post('receipts/{receipt}/seq-deposit', [SeqDepositController::class, 'store'])
            ->name('receipts.seq-deposits.store');

        Route::get('seq-report', [SeqReportController::class, 'index'])
            ->name('seq-report.index');

        Route::get('seq-report/export', SeqReportExportController::class)
            ->name('seq-report.export');

        Route::resource('charge-concepts', ChargeConceptController::class)
            ->only(['index', 'store', 'update']);

        Route::put('charge-concepts/{charge_concept}/official-link', [ChargeConceptOfficialLinkController::class, 'update'])
            ->name('charge-concepts.official-link.update');

        Route::post('official-fee-concepts', [OfficialFeeConceptController::class, 'store'])
            ->name('official-fee-concepts.store');

        Route::get('expense-classifications/imports/create', [ExpenseClassificationImportController::class, 'create'])
            ->name('expense-classifications.imports.create');

        Route::post('expense-classifications/imports', [ExpenseClassificationImportController::class, 'store'])
            ->name('expense-classifications.imports.store');

        Route::get('own-revenue/budgets', [OwnRevenueBudgetController::class, 'index'])
            ->name('own-revenue.budgets.index');

        Route::get('own-revenue/budgets/create', [OwnRevenueBudgetController::class, 'create'])
            ->name('own-revenue.budgets.create');

        Route::post('own-revenue/budgets', [OwnRevenueBudgetController::class, 'store'])
            ->name('own-revenue.budgets.store');

        Route::get('own-revenue/budgets/{budget}', [OwnRevenueBudgetController::class, 'show'])
            ->name('own-revenue.budgets.show');

        Route::put('own-revenue/budgets/{budget}', [OwnRevenueBudgetController::class, 'update'])
            ->name('own-revenue.budgets.update');

        Route::get('own-revenue/budgets/{budget}/planning', OwnRevenuePlanningController::class)
            ->name('own-revenue.budgets.planning.show');

        Route::post('own-revenue/budgets/{budget}/cog/confirm', OwnRevenueCogConfirmationController::class)
            ->name('own-revenue.budgets.cog.confirm');

        Route::post('own-revenue/budgets/{budget}/proposals/from-imports', OwnRevenueProposalCreationController::class)
            ->name('own-revenue.budgets.proposals.from-imports.store');

        Route::post('own-revenue/budgets/{budget}/planning-routes', [OwnRevenueRouteController::class, 'store'])
            ->name('own-revenue.budgets.planning-routes.store');

        Route::put('own-revenue/budgets/{budget}/planning-routes/{planningRoute}', [OwnRevenueRouteController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.planning-routes.update');

        Route::delete('own-revenue/budgets/{budget}/planning-routes/{planningRoute}', [OwnRevenueRouteController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.planning-routes.destroy');

        Route::post('own-revenue/budgets/{budget}/travel-destinations', [OwnRevenueTravelDestinationController::class, 'store'])
            ->name('own-revenue.budgets.travel-destinations.store');

        Route::put('own-revenue/budgets/{budget}/travel-destinations/{travelDestination}', [OwnRevenueTravelDestinationController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.travel-destinations.update');

        Route::delete('own-revenue/budgets/{budget}/travel-destinations/{travelDestination}', [OwnRevenueTravelDestinationController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.travel-destinations.destroy');

        Route::post('own-revenue/budgets/{budget}/travel-rates', [OwnRevenueTravelRateController::class, 'store'])
            ->name('own-revenue.budgets.travel-rates.store');

        Route::put('own-revenue/budgets/{budget}/travel-rates/{travelRate}', [OwnRevenueTravelRateController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.travel-rates.update');

        Route::delete('own-revenue/budgets/{budget}/travel-rates/{travelRate}', [OwnRevenueTravelRateController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.travel-rates.destroy');

        Route::post('own-revenue/budgets/{budget}/proposals/{proposal}/technical-needs', [OwnRevenueProposalTechnicalNeedController::class, 'store'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.technical-needs.store');

        Route::put('own-revenue/budgets/{budget}/proposals/{proposal}/technical-needs/{technicalNeed}', [OwnRevenueProposalTechnicalNeedController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.technical-needs.update');

        Route::delete('own-revenue/budgets/{budget}/proposals/{proposal}/technical-needs/{technicalNeed}', [OwnRevenueProposalTechnicalNeedController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.technical-needs.destroy');

        Route::post('own-revenue/budgets/{budget}/proposals/{proposal}/fuel-needs', [OwnRevenueProposalFuelNeedController::class, 'store'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.fuel-needs.store');

        Route::put('own-revenue/budgets/{budget}/proposals/{proposal}/fuel-needs/{fuelNeed}', [OwnRevenueProposalFuelNeedController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.fuel-needs.update');

        Route::delete('own-revenue/budgets/{budget}/proposals/{proposal}/fuel-needs/{fuelNeed}', [OwnRevenueProposalFuelNeedController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.fuel-needs.destroy');

        Route::post('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions', [OwnRevenueProposalTravelCommissionController::class, 'store'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.store');

        Route::put('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions/{travelCommission}', [OwnRevenueProposalTravelCommissionController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.update');

        Route::delete('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions/{travelCommission}', [OwnRevenueProposalTravelCommissionController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.destroy');

        Route::post('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions/{travelCommission}/participants', [OwnRevenueProposalTravelParticipantController::class, 'store'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.participants.store');

        Route::put('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions/{travelCommission}/participants/{participant}', [OwnRevenueProposalTravelParticipantController::class, 'update'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.participants.update');

        Route::delete('own-revenue/budgets/{budget}/proposals/{proposal}/travel-commissions/{travelCommission}/participants/{participant}', [OwnRevenueProposalTravelParticipantController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.proposals.travel-commissions.participants.destroy');

        Route::get('own-revenue/budgets/{budget}/imports', [OwnRevenueImportController::class, 'show'])
            ->name('own-revenue.budgets.imports.show');

        Route::get('own-revenue/budgets/{budget}/imports/files/{importFile}/preview', OwnRevenueAbprePreviewController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.preview');

        Route::post('own-revenue/budgets/{budget}/imports/files', [OwnRevenueImportFileController::class, 'store'])
            ->name('own-revenue.budgets.imports.files.store');

        Route::put('own-revenue/budgets/{budget}/imports/files/{importFile}/format', [OwnRevenueImportFileController::class, 'updateFormat'])
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.format.update');

        Route::post('own-revenue/budgets/{budget}/imports/files/{importFile}/analyze', OwnRevenueImportAnalysisController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.analyze');

        Route::post('own-revenue/budgets/{budget}/imports/files/{importFile}/issues/{issue}/decision', OwnRevenueImportDecisionController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.issues.decision.store');

        Route::post('own-revenue/budgets/{budget}/imports/files/{importFile}/abpre/confirm', OwnRevenueAbpreConfirmationController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.abpre.confirm');

        Route::post('own-revenue/budgets/{budget}/imports/files/{importFile}/work-sheet/confirm', OwnRevenueWorkSheetConfirmationController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.work-sheet.confirm');

        Route::post('own-revenue/budgets/{budget}/imports/files/{importFile}/supporting/confirm', OwnRevenueSupportingConfirmationController::class)
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.supporting.confirm');

        Route::get('own-revenue/budgets/{budget}/imports/reconciliation', OwnRevenueActivityReconciliationController::class)
            ->name('own-revenue.budgets.imports.reconciliation.show');

        Route::post('own-revenue/budgets/{budget}/imports/reconciliation/rules', OwnRevenueActivityRuleController::class)
            ->name('own-revenue.budgets.imports.reconciliation.rules.store');

        Route::post('own-revenue/budgets/{budget}/imports/reconciliation/records/{record}/activity', OwnRevenueActivityExceptionController::class)
            ->name('own-revenue.budgets.imports.reconciliation.records.activity.store');

        Route::get('own-revenue/budgets/{budget}/imports/files/{importFile}/download', [OwnRevenueImportFileController::class, 'download'])
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.download');

        Route::delete('own-revenue/budgets/{budget}/imports/files/{importFile}', [OwnRevenueImportFileController::class, 'destroy'])
            ->scopeBindings()
            ->name('own-revenue.budgets.imports.files.discard');

        Route::get('u300/imports/create', [U300ImportController::class, 'create'])
            ->name('u300.imports.create');

        Route::post('u300/imports/preview', [U300ImportController::class, 'preview'])
            ->name('u300.imports.preview');

        Route::get('u300/imports/preview', [U300ImportController::class, 'showPreview'])
            ->name('u300.imports.preview.show');

        Route::post('u300/imports', [U300ImportController::class, 'store'])
            ->name('u300.imports.store');

        Route::get('u300/programs', [U300ProgramController::class, 'index'])
            ->name('u300.programs.index');

        Route::get('u300/programs/{program}/verdict', [U300FederalVerdictController::class, 'edit'])
            ->name('u300.programs.verdict.edit');

        Route::put('u300/programs/{program}/verdict', [U300FederalVerdictController::class, 'update'])
            ->name('u300.programs.verdict.update');

        Route::get('u300/programs/{program}/adjustment', [U300BudgetAdjustmentController::class, 'edit'])
            ->name('u300.programs.adjustment.edit');

        Route::put('u300/programs/{program}/adjustment', [U300BudgetAdjustmentController::class, 'update'])
            ->name('u300.programs.adjustment.update');

        Route::get('u300/programs/{program}/cog', [U300CogConversionController::class, 'edit'])
            ->name('u300.programs.cog.edit');

        Route::put('u300/programs/{program}/cog', [U300CogConversionController::class, 'update'])
            ->name('u300.programs.cog.update');

        Route::get('u300/programs/{program}/technical-sheets', [U300TechnicalSheetController::class, 'edit'])
            ->name('u300.programs.technical-sheets.edit');

        Route::get('u300/programs/{program}/technical-sheets/export', [U300TechnicalSheetController::class, 'export'])
            ->name('u300.programs.technical-sheets.export');

        Route::get('u300/programs/{program}/technical-sheets/{line}', [U300TechnicalSheetController::class, 'editLine'])
            ->name('u300.programs.technical-sheets.lines.edit');

        Route::put('u300/programs/{program}/technical-sheets', [U300TechnicalSheetController::class, 'update'])
            ->name('u300.programs.technical-sheets.update');

        Route::get('u300/programs/{program}/execution', [U300BudgetExecutionController::class, 'index'])
            ->name('u300.programs.execution.index');

        Route::get('u300/programs/{program}/financial-reports', [U300FinancialReportController::class, 'show'])
            ->name('u300.programs.financial-reports.show');

        Route::get('u300/programs/{program}/financial-reports/export', [U300FinancialReportController::class, 'export'])
            ->name('u300.programs.financial-reports.export');

        Route::post('u300/programs/{program}/execution', [U300BudgetExecutionController::class, 'store'])
            ->name('u300.programs.execution.store');

        Route::patch('u300/programs/{program}/execution/{movement}/cancel', [U300BudgetExecutionController::class, 'cancel'])
            ->name('u300.programs.execution.cancel');

        Route::get('u300/programs/{program}/summary/export', [U300ProgramController::class, 'exportSummary'])
            ->name('u300.programs.summary.export');

        Route::get('u300/programs/{program}/summary/export-xlsx', [U300ProgramController::class, 'exportSummaryWorkbook'])
            ->name('u300.programs.summary.export-xlsx');

        Route::get('u300/programs/{program}', [U300ProgramController::class, 'show'])
            ->name('u300.programs.show');
    });
});

require __DIR__.'/settings.php';
