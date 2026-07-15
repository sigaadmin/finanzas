<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\Imports\StoreOwnRevenueActivityException;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\Imports\StoreOwnRevenueActivityExceptionRequest;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OwnRevenueActivityExceptionController extends Controller
{
    public function __invoke(
        StoreOwnRevenueActivityExceptionRequest $request,
        OwnRevenueBudget $budget,
        string $record,
        StoreOwnRevenueActivityException $storeException,
    ): RedirectResponse {
        $storeException->handle(
            $budget,
            $request->user(),
            OwnRevenueImportFormat::from($request->validated('format')),
            (int) $record,
            $request->integer('activity_id'),
            OwnRevenueActivityJustification::from($request->validated('justification')),
            $request->validated('justification_note'),
            $request->integer('expected_work_sheet_file_id'),
            $request->integer('expected_supporting_file_id'),
        );

        Inertia::flash('success', 'Excepción de actividad registrada correctamente.');

        return back();
    }
}
