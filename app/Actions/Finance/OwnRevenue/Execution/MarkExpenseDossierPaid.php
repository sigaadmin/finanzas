<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MarkExpenseDossierPaid
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reference): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('authorizeExpensePayment', $dossier->budget);
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['payment_reference' => 'Captura la referencia del pago realizado.']);
        }

        return DB::transaction(function () use ($dossier, $user, $reference): OwnRevenueExpenseDossier {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('authorizeExpensePayment', $budget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($budget, 'budget')->whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized) {
                throw ValidationException::withMessages([
                    'status' => 'Presupuesto o Pagaduría debe autorizar el pago antes de marcarlo como pagado.',
                ]);
            }

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::Paid,
                'payment_reference' => $reference,
                'paid_by' => $user->id,
                'paid_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
                'to_status' => OwnRevenueExpenseDossierStatus::Paid,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
