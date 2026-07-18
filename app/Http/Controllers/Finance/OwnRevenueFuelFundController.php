<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Fuel\OpenOwnRevenueFuelFund;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Fuel\OpenOwnRevenueFuelFundRequest;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Fuel\OwnRevenueFuelViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueFuelFundController extends Controller
{
    public function show(OwnRevenueBudget $budget, OwnRevenueFuelViewData $viewData): Response
    {
        Gate::authorize('view', $budget);

        return Inertia::render('finance/own-revenue/fuel/show', $viewData->forBudget($budget));
    }

    public function store(
        OpenOwnRevenueFuelFundRequest $request,
        OwnRevenueBudget $budget,
        OpenOwnRevenueFuelFund $open,
    ): RedirectResponse {
        $dossier = OwnRevenueExpenseDossier::query()
            ->whereBelongsTo($budget, 'budget')
            ->findOrFail($request->integer('source_expense_dossier_id'));
        $open->handle($dossier, $request->user(), $request->integer('acquired_amount_cents'));

        return to_route('finance.own-revenue.budgets.fuel.show', $budget)
            ->with('success', 'El fondo operativo de combustible quedó abierto.');
    }
}
