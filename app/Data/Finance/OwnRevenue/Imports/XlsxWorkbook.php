<?php

namespace App\Data\Finance\OwnRevenue\Imports;

use OutOfBoundsException;

final readonly class XlsxWorkbook
{
    /** @param array<string, XlsxSheet> $sheets */
    public function __construct(private array $sheets) {}

    public function sheet(string $name): XlsxSheet
    {
        return $this->sheets[$name]
            ?? throw new OutOfBoundsException("No existe la hoja {$name}.");
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /** @return array<string, XlsxSheet> */
    public function sheets(): array
    {
        return $this->sheets;
    }
}
