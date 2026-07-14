<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\U300\UpdateU300TechnicalSheets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateU300TechnicalSheetsRequest;
use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\U300\U300TechnicalSheetDocxExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
                        'default_scheduled_date' => $this->formatScheduledDate($line->exercise_month, $program->fiscal_year),
                        'description' => $line->description,
                        'sheet' => $line->technicalSheet ? [
                            'item_name' => $line->technicalSheet->item_name,
                            'objective' => $line->technicalSheet->objective,
                            'work_description' => $line->technicalSheet->work_description,
                            'technical_specs' => $line->technicalSheet->technical_specs,
                            'has_goods_profile' => filled($line->technicalSheet->goods_profile),
                            'beneficiaries' => $line->technicalSheet->beneficiaries,
                            'scheduled_date' => $this->formatScheduledDate($line->technicalSheet->scheduled_date, $program->fiscal_year),
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

        if ($request->boolean('stay_on_page') && $request->integer('return_to_line_id') > 0) {
            return to_route('finance.u300.programs.technical-sheets.lines.edit', [
                $program,
                $request->integer('return_to_line_id'),
            ]);
        }

        if ($request->boolean('stay_on_page')) {
            return to_route('finance.u300.programs.technical-sheets.edit', $program);
        }

        return to_route('finance.u300.programs.show', $program);
    }

    public function editLine(U300Program $program, U300BudgetLine $line): InertiaResponse
    {
        $line->load(
            'action',
            'expenseClassification',
            'technicalSheet',
            'budgetVersion',
        );

        abort_unless(
            $line->budgetVersion->u300_program_id === $program->id
                && $line->budgetVersion->kind === 'adjusted'
                && $line->amount_cents > 0,
            404,
        );

        $chapterCode = $line->expenseClassification?->chapter_code;
        $actionLines = $line->budgetVersion
            ->budgetLines()
            ->where('u300_action_id', $line->u300_action_id)
            ->where('amount_cents', '>', 0)
            ->with('expenseClassification')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('finance/u300/programs/technical-sheet-line', [
            'program' => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
            ],
            'line' => [
                'id' => $line->id,
                'action_number' => $line->action->number,
                'action_name' => $line->action->name,
                'cog_code' => $line->expenseClassification?->specific_item_code,
                'cog_name' => $line->expenseClassification?->specific_item_name,
                'chapter_code' => $chapterCode,
                'chapter_name' => $line->expenseClassification?->chapter_name,
                'amount_cents' => $line->amount_cents,
                'default_scheduled_date' => $this->formatScheduledDate($line->exercise_month, $program->fiscal_year),
                'sheet' => $line->technicalSheet ? [
                    'item_name' => $line->technicalSheet->item_name,
                    'objective' => $line->technicalSheet->objective,
                    'work_description' => $line->technicalSheet->work_description,
                    'technical_specs' => $line->technicalSheet->technical_specs,
                    'beneficiaries' => $line->technicalSheet->beneficiaries,
                    'scheduled_date' => $this->formatScheduledDate($line->technicalSheet->scheduled_date, $program->fiscal_year),
                    'deliverables' => $line->technicalSheet->deliverables,
                    'delivery_location' => $line->technicalSheet->delivery_location,
                    'supervisor' => $line->technicalSheet->supervisor,
                    'payment_terms' => $line->technicalSheet->payment_terms,
                ] : null,
                'goods' => collect($line->technicalSheet?->goods_profile ?? [])
                    ->map(fn (array $good): array => [
                        'unit' => (string) ($good['unit'] ?? ''),
                        'description' => (string) ($good['description'] ?? ''),
                        'minimum_quantity' => (string) ($good['minimum_quantity'] ?? ''),
                        'unit_price' => (string) ($good['unit_price'] ?? ''),
                        'specifications' => (string) ($good['specifications'] ?? ''),
                        'reference_photo' => null,
                        'reference_photo_path' => (string) ($good['reference_photo_path'] ?? ''),
                    ])
                    ->values()
                    ->all(),
                'uses_goods_list' => in_array($chapterCode, ['2000', '5000'], true),
            ],
            'action_lines' => $actionLines->map(fn (U300BudgetLine $actionLine): array => [
                'id' => $actionLine->id,
                'cog_code' => $actionLine->expenseClassification?->specific_item_code,
                'cog_name' => $actionLine->expenseClassification?->specific_item_name,
                'amount_cents' => $actionLine->amount_cents,
                'is_current' => $actionLine->is($line),
            ])->values(),
        ]);
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

    private function formatScheduledDate(?string $scheduledDate, int $fiscalYear): ?string
    {
        if ($scheduledDate === null) {
            return null;
        }

        $months = [
            'ENE' => 'Enero',
            'FEB' => 'Febrero',
            'MAR' => 'Marzo',
            'ABR' => 'Abril',
            'MAY' => 'Mayo',
            'JUN' => 'Junio',
            'JUL' => 'Julio',
            'AGO' => 'Agosto',
            'SEP' => 'Septiembre',
            'OCT' => 'Octubre',
            'NOV' => 'Noviembre',
            'DIC' => 'Diciembre',
        ];

        $normalizedDate = Str::of($scheduledDate)->trim()->upper()->toString();

        return isset($months[$normalizedDate])
            ? $months[$normalizedDate].' de '.$fiscalYear
            : $scheduledDate;
    }
}
