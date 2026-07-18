<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueExpenseRequirementStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Excepted = 'excepted';
}
