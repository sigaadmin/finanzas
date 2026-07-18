<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartExpensePurchase
{
    public function __construct(private readonly OwnRevenueExpenseRequirements $requirements) {}

    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reference): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('manageExpensePurchase', $dossier->budget);
        $this->requirements->syncForStage($dossier, OwnRevenueExpenseDossierStatus::PurchaseInProgress);
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['purchase_reference' => 'Captura la referencia de la compra o contratación.']);
        }

        return DB::transaction(function () use ($dossier, $user, $reference): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('manageExpensePurchase', $lockedBudget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::SufficiencyConfirmed) {
                throw ValidationException::withMessages([
                    'status' => 'La suficiencia debe estar confirmada antes de iniciar la compra.',
                ]);
            }
            $this->requirements->assertSatisfied($lockedDossier, OwnRevenueExpenseDossierStatus::PurchaseInProgress);

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::PurchaseInProgress,
                'purchase_reference' => $reference,
                'purchase_started_by' => $user->id,
                'purchase_started_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
                'to_status' => OwnRevenueExpenseDossierStatus::PurchaseInProgress,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
