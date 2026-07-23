<?php

namespace App\Actions\Finance\U300;

use RuntimeException;
use ZipArchive;

class InspectU300BackupArchive
{
    /**
     * @return array{fiscal_year: int, files_count: int, manifest: array<string, mixed>}
     */
    public function handle(string $archivePath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('El archivo no es un respaldo U300 válido.');
        }

        try {
            $manifestJson = $zip->getFromName('manifest.json');
            $programJson = $zip->getFromName('data/program.json');

            if (! is_string($manifestJson) || ! is_string($programJson)) {
                throw new RuntimeException('El paquete no contiene la estructura requerida.');
            }

            $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);

            if (($manifest['format_version'] ?? null) !== 1 || ! is_int($manifest['fiscal_year'] ?? null)) {
                throw new RuntimeException('La versión o ejercicio del respaldo no es válido.');
            }

            $allowedPaths = ['manifest.json', ...array_keys($manifest['files'] ?? [])];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);

                if (! is_string($entry)
                    || str_starts_with($entry, '/')
                    || str_contains($entry, '..')
                    || ! in_array($entry, $allowedPaths, true)) {
                    throw new RuntimeException('El paquete contiene una ruta no permitida.');
                }
            }

            foreach ($manifest['files'] ?? [] as $path => $metadata) {
                if (! is_string($path) || ! is_array($metadata) || str_contains($path, '..')) {
                    throw new RuntimeException('El paquete contiene una ruta inválida.');
                }

                $contents = $zip->getFromName($path);

                if (! is_string($contents) || ! hash_equals((string) ($metadata['sha256'] ?? ''), hash('sha256', $contents))) {
                    throw new RuntimeException('La integridad del respaldo no pudo verificarse.');
                }
            }

            return [
                'fiscal_year' => $manifest['fiscal_year'],
                'files_count' => count($manifest['files']),
                'manifest' => $manifest,
            ];
        } finally {
            $zip->close();
        }
    }
}
