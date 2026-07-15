<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenuePlanningViewData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenuePlanningController extends Controller
{
    public function __construct(private readonly OwnRevenuePlanningViewData $viewData) {}

    public function __invoke(Request $request, OwnRevenueBudget $budget): Response
    {
        Gate::authorize('view', $budget);

        return Inertia::render('finance/own-revenue/planning/show', $this->viewData->forBudget($budget, $request));
    }
}
