<?php

namespace App\Data\Finance\OwnRevenue;

class UnsignedBigInteger
{
    public const MAX = '18446744073709551615';

    public static function normalize(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            return $value;
        }

        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    public static function isValid(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        if (! is_string($value) || preg_match('/^(?:0|[1-9]\d*)$/', $value) !== 1) {
            return false;
        }

        $maximumLength = strlen(self::MAX);
        $valueLength = strlen($value);

        return $valueLength < $maximumLength
            || ($valueLength === $maximumLength && strcmp($value, self::MAX) <= 0);
    }
}
