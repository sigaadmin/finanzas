<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use Brick\Math\BigInteger;

class OwnRevenueProposalProjector
{
    public function __construct(private readonly PortableIntegerAmount $amounts) {}

    /** @return array{work_sheet: list<array<string, int|string>>, abpre: list<array<string, int|string>>, total_amount_cents: string} */
    public function project(OwnRevenueProposal $proposal): array
    {
        $proposal->loadMissing([
            'budget',
            'technicalNeeds.activity',
            'fuelNeeds.activity',
            'travelCommissions.activity',
        ]);
        $workSheet = [];

        foreach ($proposal->technicalNeeds as $need) {
            $this->addWorkSheetLine(
                $workSheet,
                $need->activity->id,
                $need->activity->code,
                $need->activity->name,
                $need->specific_item_code,
                $need->budget_month,
                (string) $need->getRawOriginal('budget_amount_cents'),
            );
        }
        foreach ($proposal->fuelNeeds as $need) {
            $this->addWorkSheetLine(
                $workSheet,
                $need->activity->id,
                $need->activity->code,
                $need->activity->name,
                '26101',
                $need->budget_month,
                (string) $need->getRawOriginal('budget_amount_cents'),
            );
        }
        foreach ($proposal->travelCommissions as $commission) {
            $this->addWorkSheetLine(
                $workSheet,
                $commission->activity->id,
                $commission->activity->code,
                $commission->activity->name,
                '37501',
                $commission->budget_month,
                (string) $commission->getRawOriginal('participants_amount_cents'),
            );
            if ($commission->flight_amount_cents > 0) {
                $this->addWorkSheetLine(
                    $workSheet,
                    $commission->activity->id,
                    $commission->activity->code,
                    $commission->activity->name,
                    '37101',
                    $commission->budget_month,
                    (string) $commission->getRawOriginal('flight_amount_cents'),
                );
            }
        }

        ksort($workSheet);
        $workSheetLines = array_values($workSheet);
        $abpre = [];
        foreach ($workSheetLines as $line) {
            $key = $line['specific_item_code'].'|'.str_pad((string) $line['month'], 2, '0', STR_PAD_LEFT);
            if (! isset($abpre[$key])) {
                $abpre[$key] = [
                    'responsible_unit_code' => $proposal->budget->responsible_unit_code,
                    'responsible_unit_name' => $proposal->budget->responsible_unit_name,
                    'budget_program_code' => $proposal->budget->budget_program_code,
                    'budget_program_name' => $proposal->budget->budget_program_name,
                    'component_code' => $proposal->budget->component_code,
                    'component_name' => $proposal->budget->component_name,
                    'official_activity_code' => $proposal->budget->official_activity_code,
                    'official_activity_name' => $proposal->budget->official_activity_name,
                    'specific_item_code' => $line['specific_item_code'],
                    'region_code' => '02-001',
                    'region_name' => 'Felipe Carrillo Puerto',
                    'month' => $line['month'],
                    'amount_cents' => '0',
                ];
            }
            $abpre[$key]['amount_cents'] = $this->add($abpre[$key]['amount_cents'], $line['amount_cents']);
        }
        ksort($abpre);
        $total = '0';
        foreach ($workSheetLines as $line) {
            $total = $this->add($total, $line['amount_cents']);
        }

        return ['work_sheet' => $workSheetLines, 'abpre' => array_values($abpre), 'total_amount_cents' => $total];
    }

    /** @param array<string, array<string, int|string>> $lines */
    private function addWorkSheetLine(
        array &$lines,
        int $activityId,
        string $activityCode,
        string $activityName,
        string $specificItemCode,
        int $month,
        string $amountCents,
    ): void {
        $key = $specificItemCode.'|'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'|'.$activityCode.'|'.$activityId;
        if (! isset($lines[$key])) {
            $lines[$key] = [
                'activity_id' => $activityId,
                'activity_code' => $activityCode,
                'activity_name' => $activityName,
                'specific_item_code' => $specificItemCode,
                'region_code' => '02-001',
                'region_name' => 'Felipe Carrillo Puerto',
                'month' => $month,
                'amount_cents' => '0',
            ];
        }
        $lines[$key]['amount_cents'] = $this->add($lines[$key]['amount_cents'], $amountCents);
    }

    private function add(string $left, string $right): string
    {
        if (! $this->amounts->isValid($left) || ! $this->amounts->isValid($right)) {
            throw new \OverflowException('El total de la propuesta excede el importe permitido.');
        }
        $sum = BigInteger::of($left)->plus($right);
        $value = (string) $sum;
        if (! $this->amounts->isValid($value)) {
            throw new \OverflowException('El total de la propuesta excede el importe permitido.');
        }

        return $this->amounts->normalize($value);
    }
}
