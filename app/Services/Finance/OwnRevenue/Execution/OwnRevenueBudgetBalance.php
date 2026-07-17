<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;

class OwnRevenueBudgetBalance
{
    public function modifiedCents(OwnRevenueModifiedBudgetLine $line): int
    {
        $incoming = array_key_exists('incoming_modifications_sum_amount_cents', $line->getAttributes())
            ? (int) $line->getAttribute('incoming_modifications_sum_amount_cents')
            : (int) $line->incomingModifications()->sum('amount_cents');
        $outgoing = array_key_exists('outgoing_modifications_sum_amount_cents', $line->getAttributes())
            ? (int) $line->getAttribute('outgoing_modifications_sum_amount_cents')
            : (int) $line->outgoingModifications()->sum('amount_cents');

        return $line->initial_amount_cents + $incoming - $outgoing;
    }

    public function availableCents(OwnRevenueModifiedBudgetLine $line): int
    {
        return $this->modifiedCents($line)
            - $this->reservedCents($line)
            - $this->committedCents($line)
            - $this->paidCents($line);
    }

    public function reservedCents(OwnRevenueModifiedBudgetLine $line): int
    {
        return $this->sumDossiers($line, [OwnRevenueExpenseDossierStatus::SufficiencyRequested]);
    }

    public function committedCents(OwnRevenueModifiedBudgetLine $line): int
    {
        return $this->sumDossiers($line, [
            OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
            OwnRevenueExpenseDossierStatus::PurchaseInProgress,
            OwnRevenueExpenseDossierStatus::PaymentRequested,
            OwnRevenueExpenseDossierStatus::FinanceAuthorized,
            OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
        ]);
    }

    public function paidCents(OwnRevenueModifiedBudgetLine $line): int
    {
        return $this->sumDossiers($line, [OwnRevenueExpenseDossierStatus::Paid]);
    }

    /** @param list<OwnRevenueExpenseDossierStatus> $statuses */
    private function sumDossiers(OwnRevenueModifiedBudgetLine $line, array $statuses): int
    {
        return (int) $line->expenseDossiers()
            ->whereIn('status', array_map(
                static fn (OwnRevenueExpenseDossierStatus $status): string => $status->value,
                $statuses,
            ))
            ->sum('amount_cents');
    }
}
