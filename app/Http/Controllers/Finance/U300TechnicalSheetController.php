<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\UpdateU300TechnicalSheets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateU300TechnicalSheetsRequest;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\U300\U300TechnicalSheetDocxExporter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class U300TechnicalSheetController extends Controller
{
    public function edit(U300Program $program): InertiaResponse
    {
        $program->load(
            'budgetVersions.budgetLines.action',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.technicalSheet',
        );
        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $lines = $adjustedVersion?->budgetLines
            ->filter(fn ($line): bool => $line->amount_cents > 0)
            ->sortBy('sort_order')
            ->values() ?? collect();
        $sharedTechnicalSheet = $lines
            ->first(fn ($line): bool => $line->technicalSheet !== null)
            ?->technicalSheet;

        return Inertia::render('finance/u300/programs/technical-sheets', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'shared_sheet_fields' => [
                    'delivery_location' => $sharedTechnicalSheet?->delivery_location,
                    'supervisor' => $sharedTechnicalSheet?->supervisor,
                    'payment_terms' => $sharedTechnicalSheet?->payment_terms,
                ],
                'lines' => $lines
                    ->map(fn ($line): array => [
                        'id' => $line->id,
                        'action_number' => $line->action->number,
                        'action_name' => $line->action->name,
                        'action_justification' => $line->action->justification,
                        'cog_code' => $line->expenseClassification?->specific_item_code,
                        'cog_name' => $line->expenseClassification?->specific_item_name,
                        'amount_cents' => $line->amount_cents,
                        'exercise_month' => $line->exercise_month,
                        'description' => $line->description,
                        'sheet' => $line->technicalSheet ? [
                            'item_name' => $line->technicalSheet->item_name,
                            'objective' => $line->technicalSheet->objective,
                            'work_description' => $line->technicalSheet->work_description,
                            'technical_specs' => $line->technicalSheet->technical_specs,
                            'beneficiaries' => $line->technicalSheet->beneficiaries,
                            'scheduled_date' => $line->technicalSheet->scheduled_date,
                            'deliverables' => $line->technicalSheet->deliverables,
                            'delivery_location' => $line->technicalSheet->delivery_location,
                            'supervisor' => $line->technicalSheet->supervisor,
                            'payment_terms' => $line->technicalSheet->payment_terms,
                        ] : null,
                    ]) ?? [],
            ],
        ]);
    }

    public function update(
        UpdateU300TechnicalSheetsRequest $request,
        U300Program $program,
        UpdateU300TechnicalSheets $updateTechnicalSheets,
    ): RedirectResponse {
        $updateTechnicalSheets->handle($program, $request->sheets());

        return to_route('finance.u300.programs.show', $program);
    }

    public function export(
        U300Program $program,
        U300TechnicalSheetDocxExporter $exporter,
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($exporter, $program): void {
                echo $exporter->export($program);
            },
            'fichas-tecnicas-u300-'.$program->fiscal_year.'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
