<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use Brick\Math\BigInteger;

class OwnRevenueCutReconciliation
{
    public function __construct(
        private readonly OwnRevenueProposalReadiness $readiness,
        private readonly OwnRevenueProposalFingerprint $proposalFingerprint,
        private readonly CanonicalJson $canonicalJson,
        private readonly ProportionalAmountAllocator $allocator,
    ) {}

    /** @return array<string, mixed> */
    public function forProposal(OwnRevenueProposal $proposal): array
    {
        $proposal->loadMissing([
            'budget',
            'sourceWorkSheetFile.workSheetLines.months',
            'sourceAbpreFile.abpreLines.months',
            'technicalNeeds.activity',
            'fuelNeeds.activity',
            'travelCommissions.activity',
            'travelCommissions.participants',
            'cuts',
        ]);
        $blockers = [];
        $readiness = $this->readiness->forBudget($proposal->budget);
        $sourceIds = [
            OwnRevenueImportFormat::Abpre->value => $proposal->source_abpre_file_id,
            OwnRevenueImportFormat::WorkSheet->value => $proposal->source_work_sheet_file_id,
            OwnRevenueImportFormat::TechnicalSheet->value => $proposal->source_technical_sheet_file_id,
            OwnRevenueImportFormat::Fuel->value => $proposal->source_fuel_file_id,
            OwnRevenueImportFormat::TravelExpenses->value => $proposal->source_travel_expenses_file_id,
        ];
        if (! $readiness->ready
            || $readiness->fileIds !== $sourceIds
            || ! hash_equals($readiness->fingerprint, $proposal->source_fingerprint)) {
            $blockers[] = $readiness->blockers[0] ?? 'Los archivos confirmados cambiaron; vuelve a calcular la propuesta.';
        }

        $workSheet = $this->workSheetTargets($proposal);
        $abpre = $this->abpreTargets($proposal);
        $candidates = $this->candidates($proposal);
        $targets = $this->allocateAbpreTargets($workSheet, $abpre, $candidates, $blockers);
        $cuts = $proposal->cuts->keyBy(fn ($cut): string => $cut->target_type.'|'.$cut->target_id);
        foreach ($candidates as &$candidate) {
            $cut = $cuts->get($candidate['target_type'].'|'.$candidate['target_id']);
            $candidate['distributed_amount_cents'] = $cut === null
                ? '0'
                : (string) $cut->getRawOriginal('amount_cents');
        }
        unset($candidate);

        $groups = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['group_key'];
            $groups[$key] ??= [
                'key' => $key,
                'activity_id' => $candidate['activity_id'],
                'activity_code' => $candidate['activity_code'],
                'activity_name' => $candidate['activity_name'],
                'specific_item_code' => $candidate['specific_item_code'],
                'month' => $candidate['month'],
                'calculated_amount_cents' => '0',
                'target_amount_cents' => $targets[$key] ?? '0',
                'required_cut_cents' => '0',
                'required_reduction_cents' => '0',
                'required_increase_cents' => '0',
                'distributed_cut_cents' => '0',
                'distributed_reduction_cents' => '0',
                'pending_cut_cents' => '0',
                'pending_reduction_cents' => '0',
                'candidates' => [],
            ];
            $groups[$key]['calculated_amount_cents'] = $this->add(
                $groups[$key]['calculated_amount_cents'],
                $candidate['available_amount_cents'],
            );
            $groups[$key]['distributed_cut_cents'] = $this->add(
                $groups[$key]['distributed_cut_cents'],
                $candidate['distributed_amount_cents'],
            );
            $groups[$key]['candidates'][] = $candidate;
        }
        foreach ($targets as $key => $target) {
            if (! isset($groups[$key]) && $target !== '0') {
                $blockers[] = 'La propuesta no cubre uno de los importes de la Hoja de trabajo confirmada.';
            }
        }

        foreach ($groups as &$group) {
            $calculated = BigInteger::of($group['calculated_amount_cents']);
            $target = BigInteger::of($group['target_amount_cents']);
            if ($target->isGreaterThan($calculated)) {
                $requiredReduction = BigInteger::zero();
                $requiredIncrease = $target->minus($calculated);
            } else {
                $requiredReduction = $calculated->minus($target);
                $requiredIncrease = BigInteger::zero();
            }
            $distributed = BigInteger::of($group['distributed_cut_cents']);
            $pendingReduction = $distributed->isGreaterThan($requiredReduction)
                ? '0'
                : (string) $requiredReduction->minus($distributed);
            $group['required_cut_cents'] = (string) $requiredReduction;
            $group['required_reduction_cents'] = (string) $requiredReduction;
            $group['required_increase_cents'] = (string) $requiredIncrease;
            $group['distributed_reduction_cents'] = (string) $distributed;
            $group['pending_cut_cents'] = $pendingReduction;
            $group['pending_reduction_cents'] = $pendingReduction;
            if ($distributed->isGreaterThan($requiredReduction)) {
                $blockers[] = 'La reducción distribuida supera lo requerido en una actividad, partida y mes.';
            }
        }
        unset($group);
        ksort($groups);

        $summary = [
            'calculated_amount_cents' => $this->sumColumn($groups, 'calculated_amount_cents'),
            'abpre_amount_cents' => $this->sum($abpre),
            'required_cut_cents' => $this->sumColumn($groups, 'required_cut_cents'),
            'required_reduction_cents' => $this->sumColumn($groups, 'required_reduction_cents'),
            'required_increase_cents' => $this->sumColumn($groups, 'required_increase_cents'),
            'distributed_cut_cents' => $this->sumColumn($groups, 'distributed_cut_cents'),
            'distributed_reduction_cents' => $this->sumColumn($groups, 'distributed_reduction_cents'),
            'pending_cut_cents' => $this->sumColumn($groups, 'pending_cut_cents'),
            'pending_reduction_cents' => $this->sumColumn($groups, 'pending_reduction_cents'),
        ];
        $summary['adjusted_amount_cents'] = (string) BigInteger::of($summary['calculated_amount_cents'])
            ->minus($summary['distributed_cut_cents'])
            ->plus($summary['required_increase_cents']);
        $groups = array_values($groups);
        $fingerprint = $this->canonicalJson->hash([
            'proposal' => $this->proposalFingerprint->forProposal($proposal),
            'current_sources' => $readiness->fileIds,
            'work_sheet' => $workSheet,
            'targets' => $targets,
            'abpre' => $abpre,
            'candidates' => $candidates,
        ]);

        return [
            'ready' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'fingerprint' => $fingerprint,
            'summary' => $summary,
            'groups' => $groups,
            'candidates' => $candidates,
        ];
    }

    /** @return array<string, string> */
    private function workSheetTargets(OwnRevenueProposal $proposal): array
    {
        $targets = [];
        foreach ($proposal->sourceWorkSheetFile->workSheetLines as $line) {
            foreach ($line->months as $month) {
                $key = $this->groupKey($line->own_revenue_activity_id, $line->specific_item_code, $month->month);
                $targets[$key] = $this->add($targets[$key] ?? '0', (string) $month->getRawOriginal('amount_cents'));
            }
        }
        ksort($targets);

        return $targets;
    }

    /** @return array<string, string> */
    private function abpreTargets(OwnRevenueProposal $proposal): array
    {
        $targets = [];
        foreach ($proposal->sourceAbpreFile->abpreLines as $line) {
            foreach ($line->months as $month) {
                $key = $line->specific_item_code.'|'.str_pad((string) $month->month, 2, '0', STR_PAD_LEFT);
                $targets[$key] = $this->add($targets[$key] ?? '0', (string) $month->getRawOriginal('amount_cents'));
            }
        }
        ksort($targets);

        return $targets;
    }

    /** @return list<array<string, int|string>> */
    private function candidates(OwnRevenueProposal $proposal): array
    {
        $candidates = [];
        foreach ($proposal->technicalNeeds as $need) {
            $candidates[] = $this->candidate(
                'technical', $need->id, $need->stable_key, 'Ficha técnica', $need->activity,
                $need->specific_item_code, $need->budget_month, (string) $need->getRawOriginal('budget_amount_cents'),
            );
        }
        foreach ($proposal->fuelNeeds as $need) {
            $candidates[] = $this->candidate(
                'fuel', $need->id, $need->stable_key, 'Combustible', $need->activity,
                '26101', $need->budget_month, (string) $need->getRawOriginal('budget_amount_cents'),
            );
        }
        foreach ($proposal->travelCommissions as $commission) {
            $perDiemAmount = (string) $commission->participants->sum('per_diem_amount_cents');
            $lodgingAmount = (string) $commission->participants->sum('lodging_amount_cents');
            if ($perDiemAmount !== '0') {
                $candidates[] = $this->candidate(
                    'travel_per_diem', $commission->id, $commission->stable_key.':per-diem', 'Viáticos', $commission->activity,
                    '37501', $commission->budget_month, $perDiemAmount,
                );
            }
            if ($lodgingAmount !== '0') {
                $candidates[] = $this->candidate(
                    'travel_lodging', $commission->id, $commission->stable_key.':lodging', 'Viáticos', $commission->activity,
                    '37502', $commission->budget_month, $lodgingAmount,
                );
            }
            if ($commission->flight_amount_cents > 0) {
                $candidates[] = $this->candidate(
                    'travel_flight', $commission->id, $commission->stable_key.':flight', 'Viáticos', $commission->activity,
                    '37101', $commission->budget_month, (string) $commission->getRawOriginal('flight_amount_cents'),
                );
            }
        }
        usort($candidates, fn (array $left, array $right): int => [$left['group_key'], $left['stable_key']] <=> [$right['group_key'], $right['stable_key']]);

        return $candidates;
    }

    /**
     * @param  array<string, string>  $workSheet
     * @param  array<string, string>  $abpre
     * @param  list<array<string, int|string>>  $candidates
     * @param  list<string>  $blockers
     * @return array<string, string>
     */
    private function allocateAbpreTargets(array $workSheet, array $abpre, array $candidates, array &$blockers): array
    {
        $workSheetWeights = [];
        foreach ($workSheet as $groupKey => $amount) {
            [, $item, $month] = explode('|', $groupKey);
            $workSheetWeights[$item.'|'.$month][$groupKey] = $amount;
        }
        $candidateWeights = [];
        foreach ($candidates as $candidate) {
            $itemMonth = $candidate['specific_item_code'].'|'.str_pad((string) $candidate['month'], 2, '0', STR_PAD_LEFT);
            $groupKey = (string) $candidate['group_key'];
            $candidateWeights[$itemMonth][$groupKey] = $this->add(
                $candidateWeights[$itemMonth][$groupKey] ?? '0',
                (string) $candidate['available_amount_cents'],
            );
        }

        $targets = [];
        foreach ($abpre as $itemMonth => $amount) {
            $weights = $workSheetWeights[$itemMonth] ?? $candidateWeights[$itemMonth] ?? [];
            if ($weights === [] && $amount !== '0') {
                $blockers[] = 'El ABPRE contiene un importe sin actividad compatible en la planeación.';

                continue;
            }
            foreach ($this->allocator->allocate($amount, $weights) as $groupKey => $target) {
                $targets[$groupKey] = $target;
            }
        }
        ksort($targets);

        return $targets;
    }

    /** @return array<string, int|string> */
    private function candidate(
        string $targetType,
        int $targetId,
        string $stableKey,
        string $format,
        object $activity,
        string $item,
        int $month,
        string $amount,
    ): array {
        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'stable_key' => $stableKey,
            'format' => $format,
            'activity_id' => $activity->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'specific_item_code' => $item,
            'month' => $month,
            'group_key' => $this->groupKey($activity->id, $item, $month),
            'available_amount_cents' => $amount,
        ];
    }

    private function groupKey(int $activityId, string $item, int $month): string
    {
        return $activityId.'|'.$item.'|'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string, string> $values */
    private function sum(array $values): string
    {
        return array_reduce($values, fn (string $total, string $value): string => $this->add($total, $value), '0');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function sumColumn(array $rows, string $column): string
    {
        return array_reduce($rows, fn (string $total, array $row): string => $this->add($total, $row[$column]), '0');
    }

    private function add(string $left, string $right): string
    {
        return (string) BigInteger::of($left)->plus($right);
    }
}
