<?php

namespace App\Enums\Finance\OwnRevenue\Imports;

enum OwnRevenueActivityAssignmentMode: string
{
    case GroupRule = 'group_rule';
    case AutomaticRule = 'automatic_rule';
    case IndividualException = 'individual_exception';
}
