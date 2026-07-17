<?php

namespace App\Services\Finance\OwnRevenue\Execution;

use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;

class OwnRevenueBudgetBalance
{
    public function modifiedCents(OwnRevenueModifiedBudgetLine $line): int
    {
        $incoming = (int) $line->incomingModifications()->sum('amount_cents');
        $outgoing = (int) $line->outgoingModifications()->sum('amount_cents');

        return $line->initial_amount_cents + $incoming - $outgoing;
    }

    public function availableCents(OwnRevenueModifiedBudgetLine $line): int
    {
        return $this->modifiedCents($line);
    }
}
