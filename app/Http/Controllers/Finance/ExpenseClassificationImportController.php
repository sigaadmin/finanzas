<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\ImportExpenseClassifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ImportExpenseClassificationsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseClassificationImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('finance/expense-classifications/imports/create');
    }

    public function store(
        ImportExpenseClassificationsRequest $request,
        ImportExpenseClassifications $importClassifications,
    ): RedirectResponse {
        $file = $request->file('catalog_file');
        $path = $file->store('finance/expense-classifications/imports');

        $imported = $importClassifications->handle(
            fiscalYear: (int) $request->validated('fiscal_year'),
            path: Storage::disk('local')->path($path),
        );

        return to_route('finance.expense-classifications.imports.create')
            ->with('status', "Catálogo COG importado: {$imported} partidas.");
    }
}
