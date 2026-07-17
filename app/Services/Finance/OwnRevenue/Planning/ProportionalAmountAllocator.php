<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use Brick\Math\BigInteger;

class ProportionalAmountAllocator
{
    /** @param array<string, string> $weights @return array<string, string> */
    public function allocate(string $amount, array $weights): array
    {
        $totalWeight = array_reduce(
            $weights,
            fn (BigInteger $total, string $weight): BigInteger => $total->plus($weight),
            BigInteger::zero(),
        );
        if ($totalWeight->isZero()) {
            return array_fill_keys(array_keys($weights), '0');
        }

        $allocations = [];
        $allocated = BigInteger::zero();
        foreach ($weights as $key => $weight) {
            [$target, $remainder] = BigInteger::of($weight)
                ->multipliedBy($amount)
                ->quotientAndRemainder($totalWeight);
            $allocations[] = ['key' => $key, 'target' => $target, 'remainder' => $remainder];
            $allocated = $allocated->plus($target);
        }
        usort($allocations, fn (array $left, array $right): int => $right['remainder']->compareTo($left['remainder'])
            ?: $left['key'] <=> $right['key']);
        $remaining = BigInteger::of($amount)->minus($allocated)->toInt();
        for ($index = 0; $index < $remaining; $index++) {
            $allocations[$index]['target'] = $allocations[$index]['target']->plus(1);
        }

        $result = [];
        foreach ($allocations as $allocation) {
            $result[$allocation['key']] = (string) $allocation['target'];
        }
        ksort($result);

        return $result;
    }
}
