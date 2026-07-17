<?php

namespace App\Services\Finance\OwnRevenue\Execution;

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
        return $this->modifiedCents($line);
    }
}
