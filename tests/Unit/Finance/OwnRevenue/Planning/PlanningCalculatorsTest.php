<?php

use App\Services\Finance\OwnRevenue\Planning\FixedDecimal;
use App\Services\Finance\OwnRevenue\Planning\FuelNeedCalculator;
use App\Services\Finance\OwnRevenue\Planning\TechnicalNeedCalculator;
use App\Services\Finance\OwnRevenue\Planning\TravelCommissionCalculator;

test('fixed decimal primitives preserve explicit scale without floats', function () {
    $decimal = new FixedDecimal;

    expect($decimal->parse('12.3400', 4))->toBe('123400')
        ->and($decimal->add('1.2500', '2.7500', 4))->toBe('4.0000')
        ->and($decimal->subtract('4.0000', '1.2500', 4))->toBe('2.7500')
        ->and($decimal->multiply('2.5', '100.25', 4))->toBe('250.6250')
        ->and($decimal->divideCeiling('83.3', '10', 4))->toBe('8.3300')
        ->and($decimal->roundHalfUp('250.625', 2))->toBe('250.63')
        ->and($decimal->roundCentsUpToPeso('20409'))->toBe('20500')
        ->and($decimal->roundCentsUpToMultiple('20500', 5000))->toBe('25000')
        ->and($decimal->roundCentsUpToMultiple('25000', 5000))->toBe('25000');
});

test('technical references round half up to cents', function () {
    $technical = new TechnicalNeedCalculator(new FixedDecimal);

    expect($technical->referenceCents('2.5', '100.25'))->toBe('25063');
});

test('fuel applies upward peso and fifty peso stages without incrementing exact multiples', function () {
    $fuel = new FuelNeedCalculator(new FixedDecimal);
    $rounded = $fuel->calculate('83.3', '10', '24.50');
    $exact = $fuel->calculate('100', '10', '25.00');

    expect($rounded->liters)->toBe('8.3300')
        ->and($rounded->mathematicalCents)->toBe('20409')
        ->and($rounded->roundedCents)->toBe('20500')
        ->and($rounded->budgetedCents)->toBe('25000')
        ->and($rounded->roundingDifferenceCents)->toBe('4591')
        ->and($exact->budgetedCents)->toBe('25000')
        ->and($exact->roundingDifferenceCents)->toBe('0');
});

test('travel calculates food for commission days and lodging for overnight stays', function () {
    $travel = new TravelCommissionCalculator(new FixedDecimal);
    $result = $travel->calculate('2', '10', '8', '117.31', '0');

    expect($result->perDiemCents)->toBe('234620')
        ->and($result->lodgingCents)->toBe('93848')
        ->and($result->flightCents)->toBe('0')
        ->and($result->totalCents)->toBe('328468');
});

test('calculators reject invalid scale negative values zero divisors and overflow', function (string $case) {
    $decimal = new FixedDecimal;

    $operation = match ($case) {
        'scale' => fn () => $decimal->parse('1.00001', 4),
        'negative' => fn () => (new TechnicalNeedCalculator($decimal))->referenceCents('-1', '10'),
        'zero divisor' => fn () => (new FuelNeedCalculator($decimal))->calculate('100', '0', '25'),
        'overflow' => fn () => (new TechnicalNeedCalculator($decimal))->referenceCents('9999999999999999', '9999999999999999'),
    };

    expect($operation)->toThrow(match ($case) {
        'zero divisor' => DivisionByZeroError::class,
        'overflow' => OverflowException::class,
        default => InvalidArgumentException::class,
    });
})->with(['scale', 'negative', 'zero divisor', 'overflow']);
