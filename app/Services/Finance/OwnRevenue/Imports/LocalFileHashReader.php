<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use ErrorException;

class LocalFileHashReader
{
    public function sha256(string $path): string|false
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
        );

        try {
            return hash_file('sha256', $path);
        } finally {
            restore_error_handler();
        }
    }
}
