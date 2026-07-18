<?php

use App\Models\Finance\OwnRevenue\OwnRevenueBudgetClosure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('annual close records are unique immutable and auditable', function () {
    expect(Schema::hasColumns('own_revenue_budget_closures', [
        'own_revenue_budget_id',
        'note',
        'snapshot',
        'fingerprint',
        'closed_by',
        'closed_at',
    ]))->toBeTrue();

    $closure = OwnRevenueBudgetClosure::factory()->create();

    expect($closure->budget->annualClosure->is($closure))->toBeTrue()
        ->and($closure->snapshot)->toBeArray()
        ->and($closure->closed_at)->not->toBeNull()
        ->and($closure->canonicalSnapshot())->toBeString()
        ->and(fn () => $closure->update(['note' => 'Alterada']))
        ->toThrow(LogicException::class, 'El acta anual es inmutable.')
        ->and(fn () => $closure->delete())
        ->toThrow(LogicException::class, 'El acta anual es inmutable.')
        ->and(fn () => OwnRevenueBudgetClosure::factory()->create([
            'own_revenue_budget_id' => $closure->own_revenue_budget_id,
        ]))->toThrow(QueryException::class);
});
