<?php

namespace App\Console\Commands;

use App\Actions\Settings\ResetLocalData;
use App\Enums\Settings\LocalDataResetScope;
use Illuminate\Console\Command;
use Throwable;

class ResetLocalDataCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:reset-local-data
        {scope : ventanilla, u300, own-revenue o all}
        {--force : Ejecutar sin confirmación interactiva}';

    /**
     * @var string
     */
    protected $description = 'Reinicia datos financieros exclusivamente en el entorno local';

    public function handle(ResetLocalData $reset): int
    {
        $scope = LocalDataResetScope::tryFrom((string) $this->argument('scope'));

        if ($scope === null) {
            $this->error('El alcance debe ser ventanilla, u300, own-revenue o all.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Esta operación eliminará permanentemente los datos locales de {$scope->label()}. ¿Desea continuar?",
        )) {
            $this->warn('Operación cancelada.');

            return self::FAILURE;
        }

        try {
            $result = $reset->handle($scope);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$scope->label()} se reinició correctamente: {$result->deletedRecords} registros eliminados.");

        foreach ($result->fileWarnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
