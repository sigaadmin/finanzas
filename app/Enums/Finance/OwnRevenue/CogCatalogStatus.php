<?php

namespace App\Enums\Finance\OwnRevenue;

enum CogCatalogStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
}
