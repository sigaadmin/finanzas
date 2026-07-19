<?php

namespace App\Services\Finance\OwnRevenue\Reports;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;

class OwnRevenuePlanningComparison
{
    /** @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $proposals = $budget->proposals()
            ->with([
                'basedOnProposal:id,version_number,total_amount_cents',
                'basedOnProposal.cuts:id,own_revenue_proposal_id,amount_cents',
                'cuts:id,own_revenue_proposal_id,amount_cents',
                'travelCommissions:id,own_revenue_proposal_id,uma_value',
            ])
            ->orderBy('version_number')
            ->get();
        $versions = $proposals
            ->map(fn (OwnRevenueProposal $proposal): array => $this->version($proposal))
            ->all();

        return [
            'version_count' => count($versions),
            'distributed_cut_amount_cents' => $proposals->reduce(
                fn (string $total, OwnRevenueProposal $proposal): string => bcadd(
                    $total,
                    $this->distributedCuts($proposal),
                ),
                '0',
            ),
            'versions' => $versions,
            'initial_authorization' => $this->initialAuthorization($budget),
        ];
    }

    /** @return array<string, mixed> */
    private function version(OwnRevenueProposal $proposal): array
    {
        $umaValues = $proposal->travelCommissions
            ->pluck('uma_value')
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->sort(fn (string $first, string $second): int => bccomp($first, $second, 4))
            ->values()
            ->all();
        $basedOn = $proposal->basedOnProposal;

        return [
            'id' => $proposal->id,
            'version_number' => $proposal->version_number,
            'status' => $proposal->status->value,
            'based_on_version_number' => $basedOn?->version_number,
            'total_amount_cents' => (string) $proposal->getRawOriginal('total_amount_cents'),
            'difference_from_previous_cents' => $basedOn === null
                ? null
                : bcsub(
                    (string) $proposal->getRawOriginal('total_amount_cents'),
                    (string) $basedOn->getRawOriginal('total_amount_cents'),
                ),
            'applied_cut_amount_cents' => $proposal->status === OwnRevenueProposalStatus::Adjusted
                && $basedOn !== null
                    ? $this->distributedCuts($basedOn)
                    : '0',
            'uma_values' => $umaValues,
            'has_mixed_uma' => count($umaValues) > 1,
            'calculated_at' => $proposal->calculated_at?->toISOString(),
        ];
    }

    private function distributedCuts(OwnRevenueProposal $proposal): string
    {
        return $proposal->cuts->reduce(
            fn (string $total, OwnRevenueProposalCut $cut): string => bcadd(
                $total,
                (string) $cut->getRawOriginal('amount_cents'),
            ),
            '0',
        );
    }

    /** @return array<string, int|string|null>|null */
    private function initialAuthorization(OwnRevenueBudget $budget): ?array
    {
        /** @var OwnRevenueInitialBudget|null $initialBudget */
        $initialBudget = $budget->initialBudgets()
            ->with('proposal:id,version_number,total_amount_cents')
            ->first();
        if ($initialBudget === null) {
            return null;
        }

        $proposalTotal = (string) $initialBudget->proposal->getRawOriginal('total_amount_cents');
        $authorizedTotal = (string) $initialBudget->getRawOriginal('total_amount_cents');
        $umaValue = data_get($initialBudget->snapshot, 'budget.uma_value');

        return [
            'proposal_version_number' => $initialBudget->proposal->version_number,
            'proposal_total_amount_cents' => $proposalTotal,
            'authorized_total_amount_cents' => $authorizedTotal,
            'difference_amount_cents' => bcsub($authorizedTotal, $proposalTotal),
            'uma_value' => $umaValue === null ? null : bcadd((string) $umaValue, '0', 4),
            'authorized_at' => $initialBudget->authorized_at?->toISOString(),
        ];
    }
}
