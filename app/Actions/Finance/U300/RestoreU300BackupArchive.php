<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class RestoreU300BackupArchive
{
    public function __construct(private InspectU300BackupArchive $inspector) {}

    public function handle(string $archivePath, User $restoredBy): U300Program
    {
        $preview = $this->inspector->handle($archivePath);
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('El respaldo no pudo abrirse.');
        }

        try {
            $programData = json_decode((string) $zip->getFromName('data/program.json'), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $zip->close();
        }

        if (($programData['fiscal_year'] ?? null) !== $preview['fiscal_year']) {
            throw new RuntimeException('El contenido no corresponde al ejercicio del respaldo.');
        }

        return DB::transaction(function () use ($preview, $programData, $restoredBy): U300Program {
            U300Program::query()->where('fiscal_year', $preview['fiscal_year'])->lockForUpdate()->get()->each->delete();

            return U300Program::query()->create([
                ...Arr::only($programData, [
                    'fiscal_year', 'name', 'objective', 'justification', 'requested_total_cents',
                    'approved_total_cents', 'federal_authorized_total_cents', 'responsible_name',
                    'responsible_position', 'responsible_academic_degree', 'responsible_phone',
                    'responsible_email', 'source_filename', 'source_path',
                ]),
                'imported_by' => $restoredBy->id,
            ]);
        });
    }
}
