<?php

namespace App\Enums\Finance;

enum ReceiptType: string
{
    case Internal = 'internal';
    case External = 'external';
}
