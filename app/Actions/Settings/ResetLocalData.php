<?php

namespace App\Actions\Settings;

use App\Data\Settings\LocalDataResetResult;
use App\Enums\Settings\LocalDataResetScope;
use App\Services\Settings\LocalDataResetCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class ResetLocalData
{
    public function __construct(
        private readonly LocalDataResetCatalog $catalog,
    ) {}

    public function handle(LocalDataResetScope $scope): LocalDataResetResult
    {
        if (! app()->environment('local')) {
            throw new LogicException('El reinicio sólo está disponible en local.');
        }

        if ($scope === LocalDataResetScope::All) {
            throw new LogicException('El reinicio general todavía no está habilitado.');
        }

        $deletedRecords = DB::transaction(function () use ($scope): int {
            $deletedRecords = 0;

            foreach ($this->catalog->tablesFor($scope) as $table) {
                $deletedRecords += DB::table($table)->delete();
            }

            if ($scope === LocalDataResetScope::Ventanilla) {
                $deletedRecords += DB::table('finance_folio_sequences')
                    ->whereIn('sequence_key', ['procedure', 'receipt_internal', 'receipt_external'])
                    ->delete();
            }

            return $deletedRecords;
        });

        return new LocalDataResetResult(
            scope: $scope,
            deletedRecords: $deletedRecords,
            fileWarnings: $this->deleteFileRoots($scope),
        );
    }

    /**
     * @return list<string>
     */
    private function deleteFileRoots(LocalDataResetScope $scope): array
    {
        $warnings = [];

        foreach ($this->catalog->fileRootsFor($scope) as $root) {
            try {
                Storage::disk($root['disk'])->deleteDirectory($root['path']);
            } catch (Throwable $exception) {
                Log::warning('No fue posible limpiar una carpeta durante el reinicio local.', [
                    'scope' => $scope->value,
                    'disk' => $root['disk'],
                    'path' => $root['path'],
                    'exception' => $exception::class,
                ]);

                $warnings[] = "No fue posible limpiar la carpeta {$root['path']}. Puede repetir el reinicio para intentarlo de nuevo.";
            }
        }

        return $warnings;
    }
}
