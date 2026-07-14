<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Exceptions\Finance\OwnRevenue\Imports\StoredImportFileUnavailable;
use ErrorException;
use Illuminate\Support\Facades\Storage;

class StoredImportFileHasher
{
    public function __construct(
        private readonly LocalFileHashReader $reader,
    ) {}

    /** @throws StoredImportFileUnavailable */
    public function sha256(string $disk, string $storagePath): string
    {
        $path = Storage::disk($disk)->path($storagePath);
        if (! is_file($path) || ! is_readable($path)) {
            throw new StoredImportFileUnavailable;
        }

        try {
            $hash = $this->reader->sha256($path);
        } catch (ErrorException $exception) {
            throw new StoredImportFileUnavailable(previous: $exception);
        }

        if ($hash === false) {
            throw new StoredImportFileUnavailable;
        }

        return $hash;
    }
}
