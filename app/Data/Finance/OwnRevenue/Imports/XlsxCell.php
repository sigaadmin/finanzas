<?php

namespace App\Data\Finance\OwnRevenue\Imports;

final readonly class XlsxCell
{
    public function __construct(
        public string $coordinate,
        public ?string $value,
        public ?string $formula,
    ) {}
}
