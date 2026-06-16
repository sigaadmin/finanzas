<?php

namespace App\Enums\Finance;

enum ChargeConceptType: string
{
    case Internal = 'internal';
    case External = 'external';
}
