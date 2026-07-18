<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Audit\OwnRevenueAuditTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueAuditController extends Controller
{
    public function __invoke(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueAuditTimeline $timeline,
    ): Response {
        Gate::authorize('view', $budget);

        return Inertia::render('finance/own-revenue/audit/index', [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
            ],
            'timeline' => $timeline->forBudget($budget, $request->string('type')->toString() ?: null),
            'permissions' => ['read_only' => true],
        ]);
    }
}
