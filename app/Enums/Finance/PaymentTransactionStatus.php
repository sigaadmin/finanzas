<?php

namespace App\Enums\Finance;

enum PaymentTransactionStatus: string
{
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
