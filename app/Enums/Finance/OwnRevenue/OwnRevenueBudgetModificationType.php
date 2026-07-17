<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueBudgetModificationType: string
{
    case Transfer = 'transfer';
    case Rescheduling = 'rescheduling';
}
