<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteOwnRevenueTravelDestination;
use App\Actions\Finance\OwnRevenue\Planning\StoreOwnRevenueTravelDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreOwnRevenueTravelDestinationRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueTravelDestinationController extends Controller
{
    public function store(StoreOwnRevenueTravelDestinationRequest $request, OwnRevenueBudget $budget, StoreOwnRevenueTravelDestination $action): RedirectResponse
    {
        $action->handle($budget, $request->user(), $request->validated());
        Inertia::flash('success', 'Destino guardado.');

        return back();
    }

    public function update(StoreOwnRevenueTravelDestinationRequest $request, OwnRevenueBudget $budget, OwnRevenueTravelDestination $travelDestination, StoreOwnRevenueTravelDestination $action): RedirectResponse
    {
        $action->handle($budget, $request->user(), $request->validated(), $travelDestination);
        Inertia::flash('success', 'Destino actualizado.');

        return back();
    }

    public function destroy(Request $request, OwnRevenueBudget $budget, OwnRevenueTravelDestination $travelDestination, DeleteOwnRevenueTravelDestination $action): RedirectResponse
    {
        $action->handle($budget, $travelDestination, $request->user());
        Inertia::flash('success', 'Destino eliminado.');

        return back();
    }
}
