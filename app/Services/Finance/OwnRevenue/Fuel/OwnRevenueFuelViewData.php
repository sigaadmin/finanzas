<?php

namespace App\Services\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Support\Facades\Gate;

class OwnRevenueFuelViewData
{
    public function __construct(private readonly OwnRevenueFuelSummary $summary) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $fund = $budget->fuelFund()->with(['sourceDossier:id,folio,amount_cents', 'openedBy:id,name'])->first();

        return [
            'budget' => [
                'id' => $budget->id,
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'fuel_budget_month' => $budget->fuel_budget_month,
            ],
            'fund' => $fund === null ? null : [
                'id' => $fund->id,
                'acquired_amount_cents' => (string) $fund->getRawOriginal('acquired_amount_cents'),
                'source_dossier' => [
                    'id' => $fund->sourceDossier->id,
                    'folio' => $fund->sourceDossier->folio,
                    'paid_amount_cents' => (string) $fund->sourceDossier->getRawOriginal('amount_cents'),
                ],
                'opened_by_name' => $fund->openedBy->name,
                'opened_at' => $fund->opened_at?->toISOString(),
            ],
            'summary' => $this->summary->forBudget($budget),
            'eligible_dossiers' => $budget->expenseDossiers()
                ->where('status', OwnRevenueExpenseDossierStatus::Paid)
                ->whereHas('budgetLine', fn ($query) => $query
                    ->where('specific_item_code', '26101')
                    ->where('month', $budget->fuel_budget_month))
                ->whereDoesntHave('openedFuelFund')
                ->with('budgetLine:id,specific_item_code,specific_item_name,month')
                ->orderByDesc('paid_at')
                ->get(['id', 'own_revenue_modified_budget_line_id', 'folio', 'amount_cents', 'paid_at'])
                ->map(fn ($dossier): array => [
                    'id' => $dossier->id,
                    'folio' => $dossier->folio,
                    'paid_amount_cents' => (string) $dossier->getRawOriginal('amount_cents'),
                    'paid_at' => $dossier->paid_at?->toISOString(),
                ])->all(),
            'permissions' => [
                'open_fund' => $fund === null && Gate::allows('openFuelFund', $budget),
            ],
        ];
    }
}
