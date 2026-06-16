<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use Illuminate\Support\Facades\DB;

class UpdateU300TechnicalSheets
{
    /**
     * @param  list<array{
     *     u300_budget_line_id: int,
     *     item_name: string|null,
     *     objective: string|null,
     *     work_description: string|null,
     *     technical_specs: string|null,
     *     beneficiaries: string|null,
     *     scheduled_date: string|null,
     *     deliverables: string|null,
     *     delivery_location: string|null,
     *     supervisor: string|null,
     *     payment_terms: string|null
     * }>  $sheets
     */
    public function handle(U300Program $program, array $sheets): U300Program
    {
        return DB::transaction(function () use ($program, $sheets): U300Program {
            $lineIds = collect($sheets)->pluck('u300_budget_line_id');

            $budgetLines = U300BudgetLine::query()
                ->whereIn('id', $lineIds)
                ->whereHas('budgetVersion', fn ($query) => $query
                    ->where('u300_program_id', $program->id)
                    ->where('kind', 'adjusted'))
                ->get()
                ->keyBy('id');

            foreach ($sheets as $sheetData) {
                /** @var U300BudgetLine|null $budgetLine */
                $budgetLine = $budgetLines->get($sheetData['u300_budget_line_id']);

                if (! $budgetLine) {
                    continue;
                }

                $budgetLine->technicalSheet()->updateOrCreate(
                    ['u300_budget_line_id' => $budgetLine->id],
                    collect($sheetData)->except('u300_budget_line_id')->all(),
                );
            }

            return $program->refresh()->load('budgetVersions.budgetLines.technicalSheet');
        });
    }
}
