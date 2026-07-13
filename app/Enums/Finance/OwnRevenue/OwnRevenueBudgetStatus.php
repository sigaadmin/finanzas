<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueBudgetStatus: string
{
    case Draft = 'draft';
    case ProposalCalculated = 'proposal_calculated';
    case ProposalAdjusted = 'proposal_adjusted';
    case InitialAuthorized = 'initial_authorized';
    case InExecution = 'in_execution';
    case Closed = 'closed';
}
