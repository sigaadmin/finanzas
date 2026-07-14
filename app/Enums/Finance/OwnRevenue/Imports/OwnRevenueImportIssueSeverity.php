<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueImportIssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
