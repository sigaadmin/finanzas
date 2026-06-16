<?php

namespace App\Enums\Finance;

enum ReceiptStatus: string
{
    case Issued = 'issued';
    case Reprinted = 'reprinted';
    case Cancelled = 'cancelled';
}
