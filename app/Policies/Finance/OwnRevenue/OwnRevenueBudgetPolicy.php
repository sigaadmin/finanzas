<?php

namespace App\Policies\Finance\OwnRevenue;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;

class OwnRevenueBudgetPolicy
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
    public function view(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $user->canOperateFinance();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAdministrate($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateSettings(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->canAdministrate($user);
    }

    /**
     * Determine whether the user can copy the model.
     */
    public function copy(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->canAdministrate($user);
    }

    /**
     * Determine whether the user can confirm the model's COG catalog.
     */
    public function confirmCog(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->canAdministrate($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return false;
    }

    private function canAdministrate(User $user): bool
    {
        return $user->isOwner()
            || $user->isAdmin()
            || $user->isFinanceManager();
    }
}
