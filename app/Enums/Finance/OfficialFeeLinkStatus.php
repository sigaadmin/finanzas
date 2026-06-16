<?php

namespace App\Enums\Finance;

enum OfficialFeeLinkStatus: string
{
    case PendingReview = 'pending_review';
    case Linked = 'linked';
    case NotApplicable = 'not_applicable';
}
