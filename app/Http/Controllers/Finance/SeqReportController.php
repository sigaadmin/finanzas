<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildSeqReportRows;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeqReportController extends Controller
{
    public function index(Request $request, BuildSeqReportRows $buildRows): Response
    {
        Gate::authorize('operate-finance');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $rows = $buildRows->handle($filters);

        return Inertia::render('finance/reports/seq', [
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'rows' => $rows,
            'totals' => [
                'receipts' => $rows->count(),
                'total_pesos' => $rows->sum('total_pesos'),
            ],
        ]);
    }
}
