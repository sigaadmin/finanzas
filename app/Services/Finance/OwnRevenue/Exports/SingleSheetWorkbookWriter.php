<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SingleSheetWorkbookWriter
{
    /** @param list<array<string, int|float|string>> $rows */
    public function write(string $title, array $rows): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($title);
        if ($rows !== []) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A1');
            $sheet->fromArray(array_map(fn (array $row): array => array_values($row), $rows), null, 'A2');
        }
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-export');
        (new Xlsx($book))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new \RuntimeException('No fue posible generar el archivo XLSX.');
    }
}
