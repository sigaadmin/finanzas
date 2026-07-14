<?php

namespace App\Services\Finance\OwnRevenue\Imports;

class CanonicalJson
{
    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    public function encode(mixed $value): string
    {
        return json_encode($this->canonicalize($value), JSON_THROW_ON_ERROR);
    }

    public function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(
            fn (mixed $item): mixed => $this->canonicalize($item),
            $value,
        );
    }
}
