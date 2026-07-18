<?php

namespace App\Services\Finance\OwnRevenue\Closing;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Reports\OwnRevenueInternalReportData;

class OwnRevenueAnnualCloseReview
{
    public function __construct(
        private readonly OwnRevenueInternalReportData $reports,
    ) {}

    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $report = $this->reports->forBudget($budget, []);
        $activeDossiers = $budget->expenseDossiers()
            ->whereNotIn('status', $this->terminalDossierStatuses())
            ->count();
        $pendingRequirements = OwnRevenueExpenseDossierRequirement::query()
            ->where('status', OwnRevenueExpenseRequirementStatus::Pending)
            ->whereHas('dossier', fn ($query) => $query
                ->where('own_revenue_budget_id', $budget->id))
            ->count();
        $pendingFuelCommissions = $budget->fuelFund()->first()?->commissions()
            ->where('status', OwnRevenueFuelCommissionStatus::Pending)
            ->count() ?? 0;
        $blockers = array_values(array_filter([
            $this->blocker(
                'active_expense_dossiers',
                $activeDossiers,
                'expediente que todavía requiere concluirse',
                'expedientes que todavía requieren concluirse',
            ),
            $this->blocker(
                'pending_requirements',
                $pendingRequirements,
                'requisito pendiente de atención',
                'requisitos pendientes de atención',
            ),
            $this->blocker(
                'pending_fuel_commissions',
                $pendingFuelCommissions,
                'comisión de combustible pendiente de confirmar',
                'comisiones de combustible pendientes de confirmar',
            ),
        ]));
        $stateIsEligible = in_array($budget->status, [
            OwnRevenueBudgetStatus::InitialAuthorized,
            OwnRevenueBudgetStatus::InExecution,
        ], true);
        $initialBudget = $budget->initialBudgets()->latest('id')->first();

        return [
            'eligible' => $stateIsEligible && $blockers === [],
            'state_is_eligible' => $stateIsEligible,
            'confirmation_phrase' => "CERRAR {$budget->fiscal_year}",
            'blockers' => $blockers,
            'snapshot' => [
                'schema_version' => 1,
                'budget' => [
                    'id' => $budget->id,
                    'fiscal_year' => $budget->fiscal_year,
                    'region_code' => $budget->region_code,
                    'region_name' => $budget->region_name,
                ],
                'balances' => $report['summary'],
                'expense_dossiers' => $report['expense_dossiers'],
                'fuel' => $report['fuel'],
                'modifications' => [
                    'count' => $report['modifications']['total'],
                    'transfer_amount_cents' => $report['modifications']['transfer_amount_cents'],
                    'rescheduling_amount_cents' => $report['modifications']['rescheduling_amount_cents'],
                ],
                'official_exports_count' => $initialBudget?->workbookExports()->count() ?? 0,
                'initial_authorization' => $initialBudget === null ? null : [
                    'id' => $initialBudget->id,
                    'authorized_at' => $initialBudget->authorized_at?->toISOString(),
                ],
            ],
        ];
    }

    /** @return list<string> */
    private function terminalDossierStatuses(): array
    {
        return [
            OwnRevenueExpenseDossierStatus::Paid->value,
            OwnRevenueExpenseDossierStatus::Rejected->value,
            OwnRevenueExpenseDossierStatus::Cancelled->value,
        ];
    }

    /** @return array{type: string, count: int, message: string}|null */
    private function blocker(string $type, int $count, string $singular, string $plural): ?array
    {
        if ($count === 0) {
            return null;
        }

        return [
            'type' => $type,
            'count' => $count,
            'message' => "Hay {$count} ".($count === 1 ? $singular : $plural).'.',
        ];
    }
}
