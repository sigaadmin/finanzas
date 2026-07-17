<?php

namespace App\Policies\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
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

    public function viewImports(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->view($user, $ownRevenueBudget);
    }

    public function manageImports(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::Draft
            && $this->canAdministrate($user);
    }

    public function confirmImports(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->manageImports($user, $ownRevenueBudget);
    }

    public function createProposal(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::Draft
            && $this->canAdministrate($user);
    }

    public function editProposal(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::Draft
            && ($this->canAdministrate($user) || $user->isFinanceAssistant());
    }

    public function calculateProposal(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::Draft
            && $this->canAdministrate($user);
    }

    public function createProposalRevision(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return in_array($ownRevenueBudget->status, [
            OwnRevenueBudgetStatus::ProposalCalculated,
            OwnRevenueBudgetStatus::ProposalAdjusted,
        ], true) && $this->canAdministrate($user);
    }

    public function manageProposalCuts(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::ProposalCalculated
            && $this->canAdministrate($user);
    }

    public function authorizeInitialBudget(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $ownRevenueBudget->status === OwnRevenueBudgetStatus::ProposalAdjusted
            && $this->canAdministrate($user);
    }

    public function generateWorkbookExports(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return in_array($ownRevenueBudget->status, [
            OwnRevenueBudgetStatus::InitialAuthorized,
            OwnRevenueBudgetStatus::InExecution,
            OwnRevenueBudgetStatus::Closed,
        ], true)
            && $this->canAdministrate($user);
    }

    public function manageExecution(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return in_array($ownRevenueBudget->status, [
            OwnRevenueBudgetStatus::InitialAuthorized,
            OwnRevenueBudgetStatus::InExecution,
        ], true) && $this->canAdministrate($user);
    }

    public function createExpenseDossier(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->canManageExpenseDossiers($user, $ownRevenueBudget);
    }

    public function requestExpenseSufficiency(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->canManageExpenseDossiers($user, $ownRevenueBudget);
    }

    public function confirmExpenseSufficiency(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->isExecutable($ownRevenueBudget) && $this->canAdministrate($user);
    }

    private function canManageExpenseDossiers(User $user, OwnRevenueBudget $ownRevenueBudget): bool
    {
        return $this->isExecutable($ownRevenueBudget)
            && ($this->canAdministrate($user) || $user->isFinanceAssistant());
    }

    private function isExecutable(OwnRevenueBudget $ownRevenueBudget): bool
    {
        return in_array($ownRevenueBudget->status, [
            OwnRevenueBudgetStatus::InitialAuthorized,
            OwnRevenueBudgetStatus::InExecution,
        ], true);
    }

    private function canAdministrate(User $user): bool
    {
        return $user->isOwner()
            || $user->isAdmin()
            || $user->isFinanceManager();
    }
}
