<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\StoreOwnRevenueActivityRule;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\StoreOwnRevenueActivityRuleRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueActivityRuleController extends Controller
{
    public function __invoke(
        StoreOwnRevenueActivityRuleRequest $request,
        OwnRevenueBudget $budget,
        StoreOwnRevenueActivityRule $storeRule,
    ): RedirectResponse {
        $storeRule->handle(
            $budget,
            $request->user(),
            OwnRevenueImportFormat::from($request->validated('format')),
            $request->validated('group_hash'),
            $request->integer('activity_id'),
            OwnRevenueActivityJustification::from($request->validated('justification')),
            $request->validated('justification_note'),
            $request->integer('expected_work_sheet_file_id'),
            $request->integer('expected_supporting_file_id'),
        );

        Inertia::flash('success', 'Regla de actividad aplicada correctamente.');

        return back();
    }
}
