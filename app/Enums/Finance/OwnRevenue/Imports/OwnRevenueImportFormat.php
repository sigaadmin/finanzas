<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueImportFormat: string
{
    case Abpre = 'abpre';
    case WorkSheet = 'work_sheet';
    case TechnicalSheet = 'technical_sheet';
    case Fuel = 'fuel';
    case TravelExpenses = 'travel_expenses';
}
