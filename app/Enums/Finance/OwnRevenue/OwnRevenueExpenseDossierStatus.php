<?php

namespace App\Enums\Finance\OwnRevenue;

enum OwnRevenueExpenseDossierStatus: string
{
    case Draft = 'draft';
    case SufficiencyRequested = 'sufficiency_requested';
    case SufficiencyConfirmed = 'sufficiency_confirmed';
    case PurchaseInProgress = 'purchase_in_progress';
    case PaymentRequested = 'payment_requested';
    case FinanceAuthorized = 'finance_authorized';
    case BudgetOfficeAuthorized = 'budget_office_authorized';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
