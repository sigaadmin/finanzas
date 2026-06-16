<?php

namespace App\Policies;

use App\Models\PaymentProcedure;
use App\Models\User;

class PaymentProcedurePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canOperateFinance();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentProcedure $paymentProcedure): bool
    {
        return $user->canOperateFinance();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isOwner()
            || $user->isAdmin()
            || $user->isFinanceManager()
            || $user->isFinanceAssistant();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PaymentProcedure $paymentProcedure): bool
    {
        return $paymentProcedure->status->value !== 'paid'
            && (
                $user->isOwner()
                || $user->isAdmin()
                || $user->isFinanceManager()
                || $user->isFinanceAssistant()
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentProcedure $paymentProcedure): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentProcedure $paymentProcedure): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentProcedure $paymentProcedure): bool
    {
        return false;
    }
}
