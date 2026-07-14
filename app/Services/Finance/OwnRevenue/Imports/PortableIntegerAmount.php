<?php

namespace App\Services\Finance\OwnRevenue\Imports;

class PortableIntegerAmount
{
    public const MAXIMUM = '9223372036854775807';

    public function isValid(string $amount): bool
    {
        return preg_match('/^\d+$/', $amount) === 1 && ! $this->exceedsMaximum($amount);
    }

    public function normalize(string $amount): string
    {
        return ltrim($amount, '0') ?: '0';
    }

    public function add(string $left, string $right): ?string
    {
        if (! $this->isValid($left) || ! $this->isValid($right)) {
            return null;
        }

        $left = strrev($this->normalize($left));
        $right = strrev($this->normalize($right));
        $result = '';
        $carry = 0;
        for ($index = 0, $length = max(strlen($left), strlen($right)); $index < $length; $index++) {
            $total = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($total % 10);
            $carry = intdiv($total, 10);
        }
        if ($carry > 0) {
            $result .= (string) $carry;
        }

        $sum = $this->normalize(strrev($result));

        return $this->exceedsMaximum($sum) ? null : $sum;
    }

    /** @param array<int, string> $amounts */
    public function sum(array $amounts): ?string
    {
        $sum = '0';
        foreach ($amounts as $amount) {
            $sum = $this->add($sum, $amount);
            if ($sum === null) {
                return null;
            }
        }

        return $sum;
    }

    private function exceedsMaximum(string $amount): bool
    {
        $amount = $this->normalize($amount);

        return strlen($amount) > strlen(self::MAXIMUM)
            || (strlen($amount) === strlen(self::MAXIMUM) && strcmp($amount, self::MAXIMUM) > 0);
    }
}
