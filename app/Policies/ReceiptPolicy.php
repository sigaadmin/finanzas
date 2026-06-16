<?php

namespace App\Policies;

use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
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
    public function view(User $user, Receipt $receipt): bool
    {
        return $user->canOperateFinance();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Receipt $receipt): bool
    {
        return false;
    }

    public function cancel(User $user, Receipt $receipt): bool
    {
        return in_array($receipt->status, [ReceiptStatus::Issued, ReceiptStatus::Reprinted], true)
            && (
                $user->isOwner()
                || $user->isAdmin()
                || $user->isFinanceManager()
            );
    }

    public function registerSeqDeposit(User $user, Receipt $receipt): bool
    {
        return $receipt->type === ReceiptType::External
            && in_array($receipt->status, [ReceiptStatus::Issued, ReceiptStatus::Reprinted], true)
            && $receipt->seqDeposit()->doesntExist()
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
    public function delete(User $user, Receipt $receipt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Receipt $receipt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Receipt $receipt): bool
    {
        return false;
    }
}
