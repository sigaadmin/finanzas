<?php

namespace App\Enums\Finance;

enum PaymentProcedureStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
