<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Execution\ConfirmExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\CreateOwnRevenueExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\InitializeOwnRevenueModifiedBudget;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModificationRequest;
use App\Http\Requests\Finance\OwnRevenue\Execution\StoreOwnRevenueExpenseDossierRequest;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
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

    public function storeExpenseDossier(
        StoreOwnRevenueExpenseDossierRequest $request,
        OwnRevenueBudget $budget,
        CreateOwnRevenueExpenseDossier $create,
    ): RedirectResponse {
        $data = $request->validated();
        $line = OwnRevenueModifiedBudgetLine::query()->findOrFail($data['own_revenue_modified_budget_line_id']);
        $create->handle($budget, $line, $request->user(), $data);

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'El expediente de gasto quedó guardado como borrador.');
    }

    public function requestSufficiency(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        RequestExpenseSufficiency $requestSufficiency,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $requestSufficiency->handle($expenseDossier, $request->user());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La suficiencia quedó solicitada y el importe fue reservado.');
    }

    public function confirmSufficiency(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueExpenseDossier $expenseDossier,
        ConfirmExpenseSufficiency $confirmSufficiency,
    ): RedirectResponse {
        abort_unless($expenseDossier->own_revenue_budget_id === $budget->id, 404);
        $confirmSufficiency->handle($expenseDossier, $request->user());

        return to_route('finance.own-revenue.budgets.execution.show', $budget)
            ->with('success', 'La suficiencia quedó confirmada y el importe fue comprometido.');
    }
}
