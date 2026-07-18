<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueExpenseDossierDocumentStage: string
{
    case PaymentRequest = 'payment_request';
    case RequirementEvidence = 'requirement_evidence';
    case RequirementException = 'requirement_exception';
}
