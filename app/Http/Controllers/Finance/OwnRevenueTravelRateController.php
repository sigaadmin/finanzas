<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteOwnRevenueTravelRate;
use App\Actions\Finance\OwnRevenue\Planning\StoreOwnRevenueTravelRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreOwnRevenueTravelRateRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueTravelRateController extends Controller
{
    public function store(StoreOwnRevenueTravelRateRequest $request, OwnRevenueBudget $budget, StoreOwnRevenueTravelRate $action): RedirectResponse
    {
        $action->handle($budget, $request->user(), $request->validated());
        Inertia::flash('success', 'Tarifa guardada.');

        return back();
    }

    public function update(StoreOwnRevenueTravelRateRequest $request, OwnRevenueBudget $budget, OwnRevenueTravelRate $travelRate, StoreOwnRevenueTravelRate $action): RedirectResponse
    {
        $action->handle($budget, $request->user(), $request->validated(), $travelRate);
        Inertia::flash('success', 'Tarifa actualizada.');

        return back();
    }

    public function destroy(Request $request, OwnRevenueBudget $budget, OwnRevenueTravelRate $travelRate, DeleteOwnRevenueTravelRate $action): RedirectResponse
    {
        $action->handle($budget, $travelRate, $request->user());
        Inertia::flash('success', 'Tarifa eliminada.');

        return back();
    }
}
