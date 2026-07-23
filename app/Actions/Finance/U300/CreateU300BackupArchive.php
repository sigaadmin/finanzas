<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300BackupArchive;
use App\Models\Finance\U300\U300BackupOperation;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CreateU300BackupArchive
{
    public function handle(U300Program $program, User $user, string $kind): U300BackupArchive
    {
        $program->load([
            'budgetVersions.requestedItems',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.technicalSheet',
            'budgetVersions.budgetLines.movements',
            'projects.goals.actions',
        ]);

        $data = json_encode($program->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $manifest = [
            'format_version' => 1,
            'fiscal_year' => $program->fiscal_year,
            'files' => [
                'data/program.json' => [
                    'sha256' => hash('sha256', $data),
                    'size_bytes' => strlen($data),
                ],
            ],
        ];
        $temporaryPath = tempnam(sys_get_temp_dir(), 'u300-backup-');

        if ($temporaryPath === false) {
            throw new RuntimeException('No fue posible preparar el respaldo U300.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el archivo ZIP.');
            }

            $zip->addFromString('data/program.json', $data);
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $zip->close();

            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('No fue posible leer el respaldo U300.');
            }

            $path = 'u300/backups/'.Str::uuid().'.zip';
            Storage::disk('local')->put($path, $contents);

            $archive = U300BackupArchive::query()->create([
                'fiscal_year' => $program->fiscal_year,
                'kind' => $kind,
                'disk' => 'local',
                'path' => $path,
                'original_filename' => "u300-{$program->fiscal_year}.zip",
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'manifest' => $manifest,
                'created_by' => $user->id,
            ]);

            U300BackupOperation::query()->create([
                'u300_backup_archive_id' => $archive->id,
                'fiscal_year' => $program->fiscal_year,
                'type' => 'generated',
                'status' => 'succeeded',
                'performed_by' => $user->id,
                'details' => ['programs' => 1],
            ]);

            return $archive;
        } finally {
            @unlink($temporaryPath);
        }
    }
}
