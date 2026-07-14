<?php

namespace App\Data\Finance\OwnRevenue\Imports;

use OutOfBoundsException;

final readonly class XlsxSheet
{
    /** @param array<int, XlsxRow> $rows */
    public function __construct(
        public string $name,
        private array $rows,
    ) {}

    public function row(int $number): XlsxRow
    {
        return $this->rows[$number]
            ?? throw new OutOfBoundsException("No existe la fila {$number} en la hoja {$this->name}.");
    }

    /** @return array<int, XlsxRow> */
    public function rows(): array
    {
        return $this->rows;
    }
}
