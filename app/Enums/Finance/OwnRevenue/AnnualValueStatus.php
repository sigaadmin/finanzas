<?php

namespace App\Enums\Finance\OwnRevenue;

enum AnnualValueStatus: string
{
    case PendingReview = 'pending_review';
    case Provisional = 'provisional';
    case Final = 'final';
}
