<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Execution\InitializeOwnRevenueModifiedBudget;
use App\Actions\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModificationRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExecutionViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueBudgetExecutionController extends Controller
{
    public function __construct(
        private readonly InitializeOwnRevenueModifiedBudget $initialize,
        private readonly OwnRevenueExecutionViewData $viewData,
    ) {}

    public function show(Request $request, OwnRevenueBudget $budget): Response
    {
        Gate::authorize('view', $budget);
        $initialBudget = $budget->initialBudgets()->latest('authorized_at')->first();
        abort_if($initialBudget === null, 404);
        $this->initialize->handle($initialBudget);

        return Inertia::render('finance/own-revenue/execution/show', $this->viewData->forBudget($budget->fresh()));
    }

    public function store(
        StoreOwnRevenueBudgetModificationRequest $request,
        OwnRevenueBudget $budget,
        StoreOwnRevenueBudgetModification $store,
    ): RedirectResponse {
        $store->handle($budget, $request->user(), $request->validated());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La modificación presupuestal quedó registrada.');
    }
}
