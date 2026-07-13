<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\ConfirmOwnRevenueCogCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\ConfirmOwnRevenueCogRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueCogConfirmationController extends Controller
{
    public function __invoke(
        ConfirmOwnRevenueCogRequest $request,
        OwnRevenueBudget $budget,
        ConfirmOwnRevenueCogCatalog $confirmCog,
    ): RedirectResponse {
        $confirmCog->handle($budget, $request->user());
        Inertia::flash('success', 'Catálogo COG confirmado correctamente.');

        return to_route('finance.own-revenue.budgets.show', $budget);
    }
}
