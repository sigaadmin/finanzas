<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueActivityJustification: string
{
    case WorkSheetMatch = 'work_sheet_match';
    case DescriptionClassification = 'description_classification';
    case AdministrativeCriterion = 'administrative_criterion';
    case Other = 'other';
}
