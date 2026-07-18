<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Reports\OwnRevenueInternalReportData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueInternalReportController extends Controller
{
    public function __invoke(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueInternalReportData $reports,
    ): Response {
        Gate::authorize('view', $budget);

        return Inertia::render('finance/own-revenue/reports/show', [
            ...$reports->forBudget(
                $budget,
                $request->only(['chapter_code', 'specific_item_code', 'month']),
            ),
            'permissions' => ['read_only' => true],
        ]);
    }
}
