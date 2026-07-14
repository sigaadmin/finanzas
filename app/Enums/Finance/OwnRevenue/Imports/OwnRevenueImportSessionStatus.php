<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueImportSessionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
