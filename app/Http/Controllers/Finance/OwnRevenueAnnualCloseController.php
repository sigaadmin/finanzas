<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Closing\CloseOwnRevenueBudget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Closing\StoreOwnRevenueAnnualCloseRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Closing\OwnRevenueAnnualCloseReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueAnnualCloseController extends Controller
{
    public function show(OwnRevenueBudget $budget, OwnRevenueAnnualCloseReview $review): Response
    {
        Gate::authorize('view', $budget);
        $budget->load('annualClosure.closedBy:id,name');
        $closure = $budget->annualClosure;

        return Inertia::render('finance/own-revenue/annual-close/show', [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'region_code' => $budget->region_code,
                'region_name' => $budget->region_name,
            ],
            'review' => $review->forBudget($budget),
            'closure' => $closure === null ? null : [
                'id' => $closure->id,
                'note' => $closure->note,
                'snapshot' => $closure->snapshot,
                'fingerprint' => $closure->fingerprint,
                'closed_by' => [
                    'id' => $closure->closedBy->id,
                    'name' => $closure->closedBy->name,
                ],
                'closed_at' => $closure->closed_at?->toISOString(),
            ],
            'permissions' => [
                'close' => Gate::allows('closeAnnualBudget', $budget),
            ],
        ]);
    }

    public function store(
        StoreOwnRevenueAnnualCloseRequest $request,
        OwnRevenueBudget $budget,
        CloseOwnRevenueBudget $close,
    ): RedirectResponse {
        $close->handle($budget, $request->user(), $request->string('note')->toString());

        return to_route('finance.own-revenue.budgets.annual-close.show', $budget)
            ->with('success', 'El ejercicio quedó cerrado de forma definitiva.');
    }
}
