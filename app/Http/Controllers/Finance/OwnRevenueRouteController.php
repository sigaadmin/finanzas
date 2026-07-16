<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Planning\DeleteOwnRevenueRoute;
use App\Actions\Finance\OwnRevenue\Planning\StoreOwnRevenueRoute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreOwnRevenueRouteRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OwnRevenueRouteController extends Controller
{
    public function store(
        StoreOwnRevenueRouteRequest $request,
        OwnRevenueBudget $budget,
        StoreOwnRevenueRoute $storeRoute,
    ): RedirectResponse {
        $storeRoute->handle($budget, $request->user(), $request->validated());
        Inertia::flash('success', 'Recorrido guardado.');

        return back();
    }

    public function update(
        StoreOwnRevenueRouteRequest $request,
        OwnRevenueBudget $budget,
        OwnRevenueRoute $planningRoute,
        StoreOwnRevenueRoute $storeRoute,
    ): RedirectResponse {
        $storeRoute->handle($budget, $request->user(), $request->validated(), $planningRoute);
        Inertia::flash('success', 'Recorrido actualizado.');

        return back();
    }

    public function destroy(
        Request $request,
        OwnRevenueBudget $budget,
        OwnRevenueRoute $planningRoute,
        DeleteOwnRevenueRoute $deleteRoute,
    ): RedirectResponse {
        $deleteRoute->handle($budget, $planningRoute, $request->user());
        Inertia::flash('success', 'Recorrido eliminado.');

        return back();
    }
}
