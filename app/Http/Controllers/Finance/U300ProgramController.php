<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300BudgetMovement;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\U300\U300FinancialWorkbookExporter;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class U300ProgramController extends Controller
{
    public function index(): Response
    {
        $programs = U300Program::query()
            ->with('budgetVersions')
            ->latest()
            ->get()
            ->map(fn (U300Program $program): array => [
                'id' => $program->id,
                'fiscal_year' => $program->fiscal_year,
                'name' => $program->name,
                'requested_total_cents' => $program->requested_total_cents,
                'approved_total_cents' => $program->approved_total_cents ?? 0,
                'federal_authorized_total_cents' => $program->federal_authorized_total_cents,
                'adjusted_total_cents' => $program->budgetVersions
                    ->firstWhere('kind', 'adjusted')?->total_cents ?? 0,
                'created_at' => $program->created_at?->toDateString(),
            ]);

        return Inertia::render('finance/u300/programs/index', [
            'programs' => $programs,
        ]);
    }

    public function show(U300Program $program): Response
    {
        return Inertia::render('finance/u300/programs/show', [
            'program' => $this->dashboardData($program),
        ]);
    }

    public function exportSummary(U300Program $program): StreamedResponse
    {
        $dashboard = $this->dashboardData($program);

        return response()->streamDownload(function () use ($dashboard): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, ['Acción', 'COG', 'Mes', 'Monto adecuado', 'Ejercido', 'Disponible', 'Estado']);

            foreach ($dashboard['lines'] as $line) {
                fputcsv($output, [
                    trim($line['action_number'].' '.$line['action_name']),
                    trim(($line['cog_code'] ?? 'Sin COG').' '.($line['cog_name'] ?? '')),
                    $line['exercise_month'] ?? '',
                    number_format($line['amount_cents'] / 100, 2, '.', ''),
                    number_format($line['executed_cents'] / 100, 2, '.', ''),
                    number_format($line['available_cents'] / 100, 2, '.', ''),
                    $line['status'],
                ]);
            }

            fclose($output);
        }, 'resumen-u300-'.$dashboard['fiscal_year'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportSummaryWorkbook(
        U300Program $program,
        U300FinancialWorkbookExporter $exporter,
    ): StreamedResponse {
        $dashboard = $this->dashboardData($program);

        return response()->streamDownload(function () use ($dashboard, $exporter): void {
            echo $exporter->export($dashboard);
        }, 'resumen-u300-'.$dashboard['fiscal_year'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(U300Program $program): array
    {
        $program->load([
            'budgetVersions.budgetLines.action',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.movements',
            'budgetVersions.budgetLines.technicalSheet',
            'projects.goals.actions.requestedItems',
        ]);

        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $lines = $adjustedVersion?->budgetLines
            ?->filter(fn (U300BudgetLine $line): bool => $line->amount_cents > 0)
            ->sortBy('sort_order')
            ->values() ?? collect();
        $activeMovements = $lines->flatMap(fn (U300BudgetLine $line) => $line->movements)
            ->filter(fn (U300BudgetMovement $movement): bool => $movement->cancelled_at === null);
        $cancelledMovementsCount = $lines->flatMap(fn (U300BudgetLine $line) => $line->movements)
            ->filter(fn (U300BudgetMovement $movement): bool => $movement->cancelled_at !== null)
            ->count();
        $executedCents = (int) $activeMovements->sum(
            fn (U300BudgetMovement $movement): int => $movement->type === 'reimbursement'
                ? -$movement->amount_cents
                : $movement->amount_cents
        );
        $adjustedTotalCents = (int) $lines->sum('amount_cents');

        return [
            'id' => $program->id,
            'fiscal_year' => $program->fiscal_year,
            'name' => $program->name,
            'requested_total_cents' => $program->requested_total_cents,
            'summary' => [
                'approved_total_cents' => $program->approved_total_cents ?? 0,
                'federal_authorized_total_cents' => $program->federal_authorized_total_cents,
                'adjusted_total_cents' => $adjustedTotalCents,
                'executed_cents' => $executedCents,
                'available_cents' => $adjustedTotalCents - $executedCents,
                'lines_count' => $lines->count(),
                'lines_without_cog_count' => $lines
                    ->filter(fn (U300BudgetLine $line): bool => $line->expense_classification_id === null)
                    ->count(),
                'lines_without_technical_sheet_count' => $lines
                    ->filter(fn (U300BudgetLine $line): bool => $line->technicalSheet === null)
                    ->count(),
                'active_movements_count' => $activeMovements->count(),
                'cancelled_movements_count' => $cancelledMovementsCount,
            ],
            'lines' => $lines->map(fn (U300BudgetLine $line): array => [
                'id' => $line->id,
                'action_number' => $line->action->number,
                'action_name' => $line->action->name,
                'cog_code' => $line->expenseClassification?->specific_item_code,
                'cog_name' => $line->expenseClassification?->specific_item_name,
                'exercise_month' => $line->exercise_month,
                'amount_cents' => $line->amount_cents,
                'executed_cents' => $this->executedCents($line),
                'available_cents' => $line->amount_cents - $this->executedCents($line),
                'status' => $this->lineStatus($line),
            ]),
            'actions' => $lines
                ->groupBy('u300_action_id')
                ->map(function ($actionLines) use ($program): array {
                    $firstLine = $actionLines->first();
                    $statuses = $actionLines
                        ->map(fn (U300BudgetLine $line): string => $this->lineStatus($line))
                        ->unique()
                        ->values();

                    return [
                        'action_number' => $firstLine->action->number,
                        'action_name' => $firstLine->action->name,
                        'amount_cents' => (int) $actionLines->sum('amount_cents'),
                        'executed_cents' => (int) $actionLines->sum(fn (U300BudgetLine $line): int => $this->executedCents($line)),
                        'available_cents' => (int) $actionLines->sum(fn (U300BudgetLine $line): int => $line->amount_cents - $this->executedCents($line)),
                        'status' => $statuses->count() === 1 && $statuses->first() === 'Completa'
                            ? 'Completa'
                            : 'Con pendientes',
                        'cog_lines' => $actionLines->map(fn (U300BudgetLine $line): array => [
                            'id' => $line->id,
                            'cog_code' => $line->expenseClassification?->specific_item_code,
                            'cog_name' => $line->expenseClassification?->specific_item_name,
                            'exercise_month' => $line->exercise_month,
                            'amount_cents' => $line->amount_cents,
                            'technical_sheet_url' => route('finance.u300.programs.technical-sheets.edit', $program).'#ficha-tecnica-'.$line->id,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all(),
            'projects_count' => $program->projects->count(),
            'goals_count' => $program->projects->flatMap->goals->count(),
            'actions_count' => $program->projects->flatMap->goals->flatMap->actions->count(),
        ];
    }

    private function lineStatus(U300BudgetLine $line): string
    {
        $missing = [];

        if ($line->expense_classification_id === null) {
            $missing[] = 'COG';
        }

        if ($line->technicalSheet === null) {
            $missing[] = 'ficha';
        }

        if ($missing === []) {
            return 'Completa';
        }

        return 'Pendiente '.implode(' / ', $missing);
    }

    private function executedCents(U300BudgetLine $line): int
    {
        return (int) $line->movements
            ->filter(fn (U300BudgetMovement $movement): bool => $movement->cancelled_at === null)
            ->sum(
                fn (U300BudgetMovement $movement): int => $movement->type === 'reimbursement'
                    ? -$movement->amount_cents
                    : $movement->amount_cents
            );
    }
}
