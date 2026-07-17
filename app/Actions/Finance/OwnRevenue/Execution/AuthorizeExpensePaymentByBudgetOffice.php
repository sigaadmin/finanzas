<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuthorizeExpensePaymentByBudgetOffice
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reference): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('authorizeExpensePayment', $dossier->budget);
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages([
                'budget_office_authorization_reference' => 'Captura la referencia de Presupuesto o Pagaduría.',
            ]);
        }

        return DB::transaction(function () use ($dossier, $user, $reference): OwnRevenueExpenseDossier {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('authorizeExpensePayment', $budget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($budget, 'budget')->whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::FinanceAuthorized) {
                throw ValidationException::withMessages([
                    'status' => 'Finanzas debe autorizar el pago antes de registrarlo en Presupuesto o Pagaduría.',
                ]);
            }

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
                'budget_office_authorization_reference' => $reference,
                'budget_office_authorized_by' => $user->id,
                'budget_office_authorized_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::FinanceAuthorized,
                'to_status' => OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
