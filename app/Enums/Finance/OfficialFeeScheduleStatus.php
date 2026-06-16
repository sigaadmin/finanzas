<?php

namespace App\Enums\Finance;

enum OfficialFeeScheduleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
