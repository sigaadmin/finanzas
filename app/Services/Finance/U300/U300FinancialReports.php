<?php

namespace App\Services\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300BudgetMovement;
use App\Models\Finance\U300\U300Program;
use Illuminate\Support\Collection;

class U300FinancialReports
{
    private const Months = ['AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    /**
     * @return array{months: list<string>, desglose: list<array<string, mixed>>, concentrado: list<array<string, mixed>>, presupuesto: list<array<string, mixed>>, presupuesto_totals: array{months: array<string, int>, total_cents: int}, dashboard: array<string, list<array<string, mixed>>>}
     */
    public function build(U300Program $program): array
    {
        $program->loadMissing(
            'budgetVersions.budgetLines.action.goal.project',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.movements',
        );

        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');
        $lines = $adjustedVersion?->budgetLines
            ->filter(fn (U300BudgetLine $line): bool => $line->amount_cents > 0 && $line->expenseClassification !== null)
            ->sortBy('sort_order')
            ->values() ?? collect();

        return [
            'months' => self::Months,
            'desglose' => $this->desglose($lines),
            'concentrado' => $this->concentrado($lines),
            'presupuesto' => $this->presupuesto($lines),
            'presupuesto_totals' => $this->presupuestoTotals($lines),
            'dashboard' => $this->dashboard($lines),
        ];
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function desglose(Collection $lines): array
    {
        return $lines
            ->map(fn (U300BudgetLine $line): array => [
                'project' => $this->label($line->action->goal->project->number, $line->action->goal->project->name, true),
                'goal' => trim($line->action->goal->number.' '.$line->action->goal->description),
                'action' => trim($line->action->number.' '.$line->action->name),
                'cog_code' => $line->expenseClassification?->specific_item_code,
                'cog_name' => $line->expenseClassification?->specific_item_name,
                'amount_cents' => $line->amount_cents,
                'month' => $line->exercise_month,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function concentrado(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => $line->expenseClassification?->specific_item_code ?? '')
            ->map(function (Collection $classificationLines): array {
                $firstLine = $classificationLines->first();
                $amountCents = (int) $classificationLines->sum('amount_cents');
                $committedCents = (int) $classificationLines->sum(fn (U300BudgetLine $line): int => $this->movementCents($line, ['commitment']));
                $executedCents = (int) $classificationLines->sum(fn (U300BudgetLine $line): int => $this->movementCents($line, ['expense']) - $this->movementCents($line, ['reimbursement']));

                return [
                    'cog_code' => $firstLine->expenseClassification?->specific_item_code,
                    'cog_name' => $firstLine->expenseClassification?->specific_item_name,
                    'amount_cents' => $amountCents,
                    'committed_cents' => $committedCents,
                    'executed_cents' => $executedCents,
                    'available_cents' => $amountCents - $committedCents - $executedCents,
                ];
            })
            ->sortBy('cog_code')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function presupuesto(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => $line->expenseClassification?->specific_item_code ?? '')
            ->map(function (Collection $classificationLines): array {
                $firstLine = $classificationLines->first();
                $months = collect(self::Months)
                    ->mapWithKeys(fn (string $month): array => [
                        $month => (int) $classificationLines->where('exercise_month', $month)->sum('amount_cents'),
                    ])
                    ->all();

                return [
                    'cog_code' => $firstLine->expenseClassification?->specific_item_code,
                    'cog_name' => $firstLine->expenseClassification?->specific_item_name,
                    'months' => $months,
                    'total_cents' => array_sum($months),
                ];
            })
            ->sortBy('cog_code')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return array{months: array<string, int>, total_cents: int}
     */
    private function presupuestoTotals(Collection $lines): array
    {
        $months = collect(self::Months)
            ->mapWithKeys(fn (string $month): array => [
                $month => (int) $lines->where('exercise_month', $month)->sum('amount_cents'),
            ])
            ->all();

        return [
            'months' => $months,
            'total_cents' => array_sum($months),
        ];
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return array{by_action: list<array<string, mixed>>, by_partida: list<array<string, mixed>>, by_chapter: list<array<string, mixed>>, partida_by_month: list<array<string, mixed>>}
     */
    private function dashboard(Collection $lines): array
    {
        return [
            'by_action' => $this->amountsByAction($lines),
            'by_partida' => $this->amountsByPartida($lines),
            'by_chapter' => $this->amountsByChapter($lines),
            'partida_by_month' => $this->amountsByPartidaAndMonth($lines),
        ];
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function amountsByAction(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => (string) $line->action->id)
            ->map(function (Collection $actionLines): array {
                $firstLine = $actionLines->first();

                return [
                    'label' => trim($firstLine->action->number.' '.$firstLine->action->name),
                    'amount_cents' => (int) $actionLines->sum('amount_cents'),
                ];
            })
            ->sortByDesc('amount_cents')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function amountsByPartida(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => $line->expenseClassification?->specific_item_code ?? '')
            ->map(function (Collection $classificationLines): array {
                $firstLine = $classificationLines->first();

                return [
                    'label' => trim($firstLine->expenseClassification?->specific_item_code.' '.$firstLine->expenseClassification?->specific_item_name),
                    'amount_cents' => (int) $classificationLines->sum('amount_cents'),
                ];
            })
            ->sortByDesc('amount_cents')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function amountsByChapter(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => $line->expenseClassification?->chapter_code ?? '')
            ->map(function (Collection $chapterLines): array {
                $firstLine = $chapterLines->first();

                return [
                    'label' => trim($firstLine->expenseClassification?->chapter_code.' '.$firstLine->expenseClassification?->chapter_name),
                    'amount_cents' => (int) $chapterLines->sum('amount_cents'),
                ];
            })
            ->sortByDesc('amount_cents')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, U300BudgetLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function amountsByPartidaAndMonth(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (U300BudgetLine $line): string => $line->expenseClassification?->specific_item_code ?? '')
            ->map(function (Collection $classificationLines): array {
                $firstLine = $classificationLines->first();
                $months = collect(self::Months)
                    ->mapWithKeys(fn (string $month): array => [
                        $month => (int) $classificationLines->where('exercise_month', $month)->sum('amount_cents'),
                    ])
                    ->all();

                return [
                    'label' => trim($firstLine->expenseClassification?->specific_item_code.' '.$firstLine->expenseClassification?->specific_item_name),
                    'months' => $months,
                    'total_cents' => array_sum($months),
                ];
            })
            ->sortByDesc('total_cents')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $types
     */
    private function movementCents(U300BudgetLine $line, array $types): int
    {
        return (int) $line->movements
            ->filter(fn (U300BudgetMovement $movement): bool => $movement->cancelled_at === null && in_array($movement->type, $types, true))
            ->sum('amount_cents');
    }

    private function label(string $number, string $text, bool $dotIntegerNumber = false): string
    {
        $prefix = $dotIntegerNumber && preg_match('/^\d+$/', $number) === 1
            ? $number.'.'
            : $number;

        return trim($prefix.' '.$text);
    }
}
