<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueFuelCommissionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
}
