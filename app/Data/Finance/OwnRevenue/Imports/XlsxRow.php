<?php

namespace App\Data\Finance\OwnRevenue\Imports;

use OutOfBoundsException;

final readonly class XlsxRow
{
    /** @param array<string, XlsxCell> $cells */
    public function __construct(
        public int $number,
        private array $cells,
    ) {}

    public function cell(string $column): XlsxCell
    {
        $column = strtoupper($column);

        return $this->cells[$column]
            ?? throw new OutOfBoundsException("No existe la celda {$column}{$this->number}.");
    }

    /** @return array<string, XlsxCell> */
    public function cells(): array
    {
        return $this->cells;
    }
}
