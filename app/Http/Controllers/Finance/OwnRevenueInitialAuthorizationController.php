<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\AuthorizeOwnRevenueInitialBudget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\AuthorizeOwnRevenueInitialBudgetRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueInitialAuthorizationController extends Controller
{
    public function __invoke(
        AuthorizeOwnRevenueInitialBudgetRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueProposal $proposal,
        AuthorizeOwnRevenueInitialBudget $authorize,
    ): RedirectResponse {
        $authorize->handle($budget, $proposal, $request->user(), $request->validated('authorization_fingerprint'));
        Inertia::flash('success', 'El presupuesto inicial quedó autorizado y ya no admite cambios.');

        return to_route('finance.own-revenue.budgets.planning.show', ['budget' => $budget]);
    }
}
