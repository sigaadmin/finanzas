<?php

namespace App\Actions\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOwnRevenueCogCatalog
{
    public function handle(OwnRevenueBudget $budget, User $confirmedBy): OwnRevenueBudget
    {
        return DB::transaction(function () use ($budget, $confirmedBy): OwnRevenueBudget {
            $budget = OwnRevenueBudget::query()
                ->whereKey($budget->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! ExpenseClassification::query()->where('fiscal_year', $budget->fiscal_year)->exists()) {
                throw ValidationException::withMessages([
                    'catalog' => 'No se puede confirmar un catálogo COG sin partidas.',
                ]);
            }

            if ($budget->cog_status === CogCatalogStatus::Confirmed) {
                if ($budget->cog_confirmed_by === null || $budget->cog_confirmed_at === null) {
                    $this->throwIncoherentState();
                }

                return $budget;
            }

            if ($budget->cog_status !== CogCatalogStatus::PendingConfirmation
                || $budget->cog_confirmed_by !== null
                || $budget->cog_confirmed_at !== null) {
                $this->throwIncoherentState();
            }

            $budget->update([
                'cog_status' => CogCatalogStatus::Confirmed,
                'cog_confirmed_by' => $confirmedBy->getKey(),
                'cog_confirmed_at' => now(),
            ]);

            return $budget->refresh();
        });
    }

    private function throwIncoherentState(): never
    {
        throw ValidationException::withMessages([
            'catalog' => 'El estado de confirmación del catálogo COG es incoherente.',
        ]);
    }
}
