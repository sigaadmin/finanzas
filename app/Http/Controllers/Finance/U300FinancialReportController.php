<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\U300\U300FinancialReports;
use App\Services\Finance\U300\U300FinancialReportsWorkbookExporter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class U300FinancialReportController extends Controller
{
    public function show(U300Program $program, U300FinancialReports $reports): InertiaResponse
    {
        return Inertia::render('finance/u300/programs/financial-reports', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
            ],
            'reports' => $reports->build($program),
        ]);
    }

    public function export(
        U300Program $program,
        U300FinancialReports $reports,
        U300FinancialReportsWorkbookExporter $exporter,
    ): StreamedResponse {
        $reportData = $reports->build($program);

        return response()->streamDownload(function () use ($reportData, $exporter): void {
            echo $exporter->export($reportData);
        }, 'reportes-financieros-u300-'.$program->fiscal_year.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
