<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateOwnRevenueProposalRevision
{
    public function handle(
        OwnRevenueBudget $budget,
        OwnRevenueProposal $source,
        User $user,
    ): OwnRevenueProposal {
        Gate::forUser($user)->authorize('createProposalRevision', $budget);

        return DB::transaction(function () use ($budget, $source, $user): OwnRevenueProposal {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedSource = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($source->id);
            if ($lockedSource->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }
            Gate::forUser($user)->authorize('createProposalRevision', $lockedBudget);
            if (! in_array($lockedSource->status, [OwnRevenueProposalStatus::Calculated, OwnRevenueProposalStatus::Adjusted], true)) {
                throw new AuthorizationException;
            }

            $existing = OwnRevenueProposal::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->where('based_on_proposal_id', $lockedSource->id)
                ->where('status', OwnRevenueProposalStatus::Draft)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $revision = OwnRevenueProposal::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'version_number' => ((int) $lockedBudget->proposals()->lockForUpdate()->max('version_number')) + 1,
                'status' => OwnRevenueProposalStatus::Draft,
                'based_on_proposal_id' => $lockedSource->id,
                'source_abpre_file_id' => $lockedSource->source_abpre_file_id,
                'source_work_sheet_file_id' => $lockedSource->source_work_sheet_file_id,
                'source_technical_sheet_file_id' => $lockedSource->source_technical_sheet_file_id,
                'source_fuel_file_id' => $lockedSource->source_fuel_file_id,
                'source_travel_expenses_file_id' => $lockedSource->source_travel_expenses_file_id,
                'source_fingerprint' => $lockedSource->source_fingerprint,
                'total_amount_cents' => $lockedSource->total_amount_cents,
                'created_by' => $user->id,
            ]);

            $this->copyRows($lockedSource, $revision);
            $lockedBudget->update(['status' => OwnRevenueBudgetStatus::Draft]);

            return $revision->refresh();
        }, attempts: 3);
    }

    private function copyRows(OwnRevenueProposal $source, OwnRevenueProposal $revision): void
    {
        foreach ($source->technicalNeeds()->lockForUpdate()->get() as $need) {
            $copy = $this->copyProposalRow($need, $revision);
            $this->copyCorrections($need, $copy, $revision);
        }
        foreach ($source->fuelNeeds()->lockForUpdate()->get() as $need) {
            $copy = $this->copyProposalRow($need, $revision);
            $this->copyCorrections($need, $copy, $revision);
        }
        foreach ($source->travelCommissions()->lockForUpdate()->get() as $commission) {
            /** @var OwnRevenueProposalTravelCommission $commissionCopy */
            $commissionCopy = $this->copyProposalRow($commission, $revision);
            $this->copyCorrections($commission, $commissionCopy, $revision);
            foreach ($commission->participants()->lockForUpdate()->get() as $participant) {
                $participantCopy = $participant->replicate([
                    'id', 'own_revenue_proposal_travel_commission_id', 'own_revenue_proposal_id',
                    'own_revenue_budget_id', 'created_at', 'updated_at',
                ]);
                $participantCopy->own_revenue_proposal_travel_commission_id = $commissionCopy->id;
                $participantCopy->own_revenue_proposal_id = $revision->id;
                $participantCopy->own_revenue_budget_id = $revision->own_revenue_budget_id;
                $participantCopy->save();
                $this->copyCorrections($participant, $participantCopy, $revision);
            }
        }
    }

    private function copyProposalRow(Model $source, OwnRevenueProposal $revision): Model
    {
        $copy = $source->replicate([
            'id', 'own_revenue_proposal_id', 'own_revenue_budget_id', 'created_at', 'updated_at',
        ]);
        $copy->own_revenue_proposal_id = $revision->id;
        $copy->own_revenue_budget_id = $revision->own_revenue_budget_id;
        $copy->save();

        return $copy;
    }

    private function copyCorrections(Model $source, Model $target, OwnRevenueProposal $revision): void
    {
        if (! method_exists($source, 'corrections') || ! method_exists($target, 'corrections')) {
            return;
        }
        foreach ($source->corrections()->lockForUpdate()->get() as $correction) {
            $target->corrections()->create([
                'own_revenue_proposal_id' => $revision->id,
                'field' => $correction->field,
                'old_value' => $correction->old_value,
                'new_value' => $correction->new_value,
                'justification' => $correction->justification,
                'corrected_by' => $correction->corrected_by,
                'corrected_at' => $correction->corrected_at,
            ]);
        }
    }
}
