<?php

namespace App\Actions\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use Brick\Math\BigInteger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreProposalCut
{
    public function __construct(private readonly OwnRevenueCutReconciliation $reconciliation) {}

    /** @param list<array<string, mixed>> $cuts */
    public function handle(
        OwnRevenueProposal $proposal,
        User $user,
        array $cuts,
        string $expectedFingerprint,
    ): void {
        Gate::forUser($user)->authorize('manageProposalCuts', $proposal->budget);

        DB::transaction(function () use ($proposal, $user, $cuts, $expectedFingerprint): void {
            $lockedProposal = OwnRevenueProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $budget = $lockedProposal->budget()->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('manageProposalCuts', $budget);
            if ($lockedProposal->status !== OwnRevenueProposalStatus::Calculated) {
                throw new AuthorizationException;
            }
            OwnRevenueImportFile::query()->whereBelongsTo($budget, 'budget')->lockForUpdate()->get();
            OwnRevenueProposalCut::query()->whereBelongsTo($lockedProposal, 'proposal')->lockForUpdate()->get();
            $lockedProposal->unsetRelations();
            $lockedProposal->setRelation('budget', $budget);
            $reconciliation = $this->reconciliation->forProposal($lockedProposal);
            if (! hash_equals($reconciliation['fingerprint'], $expectedFingerprint) || ! $reconciliation['ready']) {
                throw ValidationException::withMessages([
                    'reconciliation_fingerprint' => $reconciliation['blockers'][0]
                        ?? 'La propuesta o los archivos confirmados cambiaron; actualiza la página.',
                ]);
            }

            $candidates = collect($reconciliation['candidates'])
                ->keyBy(fn (array $candidate): string => $candidate['target_type'].'|'.$candidate['target_id']);
            $groups = collect($reconciliation['groups'])->keyBy('key');
            $normalized = [];
            $distributedByGroup = [];
            foreach ($cuts as $cut) {
                $key = $cut['target_type'].'|'.$cut['target_id'];
                $candidate = $candidates->get($key);
                if ($candidate === null
                    || $candidate['stable_key'] !== $cut['stable_key']
                    || $candidate['specific_item_code'] !== $cut['specific_item_code']
                    || isset($normalized[$key])) {
                    $this->invalid('La reducción no corresponde a una necesidad vigente de esta propuesta.');
                }
                $amount = BigInteger::of($cut['amount_cents']);
                if ($amount->isGreaterThan($candidate['available_amount_cents'])) {
                    $this->invalid('Una reducción supera el importe disponible de la necesidad.');
                }
                $groupKey = $candidate['group_key'];
                $distributedByGroup[$groupKey] = ($distributedByGroup[$groupKey] ?? BigInteger::zero())->plus($amount);
                $normalized[$key] = ['candidate' => $candidate, 'amount' => $amount];
            }
            foreach ($distributedByGroup as $groupKey => $distributed) {
                if ($distributed->isGreaterThan($groups[$groupKey]['required_cut_cents'])) {
                    $this->invalid('La reducción distribuida supera la requerida para una actividad, partida y mes.');
                }
            }

            $lockedProposal->cuts()->delete();
            foreach ($normalized as ['candidate' => $candidate, 'amount' => $amount]) {
                if ($amount->isZero()) {
                    continue;
                }
                $lockedProposal->cuts()->create([
                    'own_revenue_activity_id' => $candidate['activity_id'],
                    'target_type' => $candidate['target_type'],
                    'target_id' => $candidate['target_id'],
                    'stable_key' => $candidate['stable_key'],
                    'specific_item_code' => $candidate['specific_item_code'],
                    'budget_month' => $candidate['month'],
                    'available_amount_cents' => $candidate['available_amount_cents'],
                    'amount_cents' => (string) $amount,
                    'created_by' => $user->id,
                ]);
            }
        }, attempts: 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['cuts' => $message]);
    }
}
