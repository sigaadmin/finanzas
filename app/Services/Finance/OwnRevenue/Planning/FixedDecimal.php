<?php

namespace App\Services\Finance\OwnRevenue\Planning;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use DivisionByZeroError;
use InvalidArgumentException;
use OverflowException;

class FixedDecimal
{
    public function parse(string $value, int $scale): string
    {
        return (string) $this->decimal($value, $scale)
            ->toScale($scale, RoundingMode::Unnecessary)
            ->getUnscaledValue();
    }

    public function add(string $left, string $right, int $scale): string
    {
        return (string) $this->decimal($left, $scale)
            ->plus($this->decimal($right, $scale))
            ->toScale($scale, RoundingMode::Unnecessary);
    }

    public function subtract(string $left, string $right, int $scale): string
    {
        return (string) $this->decimal($left, $scale)
            ->minus($this->decimal($right, $scale))
            ->toScale($scale, RoundingMode::Unnecessary);
    }

    public function multiply(string $left, string $right, int $scale): string
    {
        return (string) $this->decimal($left, $scale)
            ->multipliedBy($this->decimal($right, $scale))
            ->toScale($scale, RoundingMode::HalfUp);
    }

    public function divideCeiling(string $dividend, string $divisor, int $scale): string
    {
        $divisorValue = $this->decimal($divisor, $scale);
        if ($divisorValue->isZero()) {
            throw new DivisionByZeroError('No se puede dividir entre cero.');
        }

        return (string) $this->decimal($dividend, $scale)
            ->dividedBy($divisorValue, $scale, RoundingMode::Ceiling);
    }

    public function roundHalfUp(string $value, int $scale): string
    {
        return (string) $this->decimal($value)->toScale($scale, RoundingMode::HalfUp);
    }

    public function roundCentsUpToPeso(string $cents): string
    {
        return $this->roundCentsUpToMultiple($cents, 100);
    }

    public function roundCentsUpToMultiple(string $cents, int $multiple): string
    {
        if ($multiple <= 0) {
            throw new InvalidArgumentException('El múltiplo debe ser mayor que cero.');
        }
        $amount = $this->cents($cents);
        [$quotient, $remainder] = $amount->quotientAndRemainder($multiple);
        if (! $remainder->isZero()) {
            $quotient = $quotient->plus(1);
        }

        return $this->portableCents($quotient->multipliedBy($multiple));
    }

    public function centsHalfUp(string $pesos): string
    {
        $cents = $this->nonNegative($pesos)->toScale(2, RoundingMode::HalfUp)->getUnscaledValue();

        return $this->portableCents($cents);
    }

    public function centsInteger(string $cents): string
    {
        return $this->portableCents($this->cents($cents));
    }

    public function requireNonNegative(string $value, int $maxScale = 4): string
    {
        return (string) $this->nonNegative($value, $maxScale);
    }

    public function compare(string $left, string $right): int
    {
        return $this->decimal($left)->compareTo($this->decimal($right));
    }

    private function decimal(string $value, ?int $maxScale = null): BigDecimal
    {
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('El valor debe ser un número decimal escrito sin exponentes.');
        }
        $decimal = BigDecimal::of($value);
        if ($maxScale !== null && ($maxScale < 0 || $decimal->getScale() > $maxScale)) {
            throw new InvalidArgumentException("El valor admite como máximo {$maxScale} decimales.");
        }

        return $decimal;
    }

    private function nonNegative(string $value, ?int $maxScale = null): BigDecimal
    {
        $decimal = $this->decimal($value, $maxScale);
        if ($decimal->isNegative()) {
            throw new InvalidArgumentException('El valor no puede ser negativo.');
        }

        return $decimal;
    }

    private function cents(string $value): BigInteger
    {
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('El importe en centavos debe ser un entero no negativo.');
        }

        return BigInteger::of($value);
    }

    private function portableCents(BigInteger $value): string
    {
        if ($value->isNegative() || $value->compareTo((string) PHP_INT_MAX) > 0) {
            throw new OverflowException('El importe excede el máximo que puede almacenarse.');
        }

        return (string) $value;
    }
}
