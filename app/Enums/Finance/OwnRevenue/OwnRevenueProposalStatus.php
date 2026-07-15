<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueProposalStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Adjusted = 'adjusted';
}
