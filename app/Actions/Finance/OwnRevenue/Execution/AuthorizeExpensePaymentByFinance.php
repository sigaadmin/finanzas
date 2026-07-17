<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuthorizeExpensePaymentByFinance
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reference): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('authorizeExpensePayment', $dossier->budget);
        $reference = $this->validatedReference($reference);

        return DB::transaction(function () use ($dossier, $user, $reference): OwnRevenueExpenseDossier {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('authorizeExpensePayment', $budget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($budget, 'budget')->whereKey($dossier->id)->lockForUpdate()->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::PaymentRequested) {
                throw ValidationException::withMessages(['status' => 'El pago debe estar solicitado antes de autorizarlo en Finanzas.']);
            }

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::FinanceAuthorized,
                'finance_authorization_reference' => $reference,
                'finance_authorized_by' => $user->id,
                'finance_authorized_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::PaymentRequested,
                'to_status' => OwnRevenueExpenseDossierStatus::FinanceAuthorized,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }

    private function validatedReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['finance_authorization_reference' => 'Captura la referencia de autorización de Finanzas.']);
        }

        return $reference;
    }
}
