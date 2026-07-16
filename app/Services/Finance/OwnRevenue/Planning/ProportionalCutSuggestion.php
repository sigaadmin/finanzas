<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use Brick\Math\BigInteger;
use InvalidArgumentException;

class ProportionalCutSuggestion
{
    /** @param list<array<string, mixed>> $groups @return array<string, string> */
    public function suggest(array $groups): array
    {
        $suggestion = [];
        foreach ($groups as $group) {
            $required = BigInteger::of($group['required_cut_cents']);
            if ($required->isZero()) {
                continue;
            }
            $available = array_reduce(
                $group['candidates'],
                fn (BigInteger $total, array $candidate): BigInteger => $total->plus($candidate['available_amount_cents']),
                BigInteger::zero(),
            );
            if ($required->isGreaterThan($available)) {
                throw new InvalidArgumentException('La reducción requerida supera el importe disponible.');
            }

            $allocations = [];
            $allocated = BigInteger::zero();
            foreach ($group['candidates'] as $candidate) {
                [$amount, $remainder] = BigInteger::of($candidate['available_amount_cents'])
                    ->multipliedBy($required)
                    ->quotientAndRemainder($available);
                $allocations[] = [
                    'stable_key' => $candidate['stable_key'],
                    'amount' => $amount,
                    'remainder' => $remainder,
                ];
                $allocated = $allocated->plus($amount);
            }
            usort($allocations, fn (array $left, array $right): int => $right['remainder']->compareTo($left['remainder'])
                ?: $left['stable_key'] <=> $right['stable_key']);
            $remaining = $required->minus($allocated)->toInt();
            for ($index = 0; $index < $remaining; $index++) {
                $allocations[$index]['amount'] = $allocations[$index]['amount']->plus(1);
            }
            foreach ($allocations as $allocation) {
                $suggestion[$allocation['stable_key']] = (string) $allocation['amount'];
            }
        }
        ksort($suggestion);

        return $suggestion;
    }
}
