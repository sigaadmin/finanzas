<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierTransition;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueBudgetClosure;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Audit\OwnRevenueAuditTimeline;

/** @return array{budget: OwnRevenueBudget, actor: User} */
function auditTimelineFixture(): array
{
    $actor = User::factory()->create(['name' => 'Responsable de prueba']);
    $budget = OwnRevenueBudget::factory()->for($actor, 'createdBy')->create([
        'status' => OwnRevenueBudgetStatus::Closed,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    $initialBudget = OwnRevenueInitialBudget::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'authorized_by' => $actor->id,
        'authorized_at' => now()->subDays(8),
    ]);
    $source = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'month' => 4,
    ]);
    $destination = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'expense_classification_id' => $source->expense_classification_id,
        'month' => 5,
    ]);
    OwnRevenueBudgetModification::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'source_line_id' => $source->id,
        'destination_line_id' => $destination->id,
        'type' => OwnRevenueBudgetModificationType::Transfer,
        'recorded_by' => $actor->id,
        'recorded_at' => now()->subDays(6),
    ]);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_modified_budget_line_id' => $source->id,
        'status' => OwnRevenueExpenseDossierStatus::Paid,
    ]);
    OwnRevenueExpenseDossierTransition::factory()->create([
        'own_revenue_expense_dossier_id' => $dossier->id,
        'to_status' => OwnRevenueExpenseDossierStatus::Paid,
        'actor_id' => $actor->id,
        'occurred_at' => now()->subDays(5),
    ]);
    $fund = OwnRevenueFuelFund::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'source_expense_dossier_id' => $dossier->id,
        'opened_by' => $actor->id,
        'opened_at' => now()->subDays(4),
    ]);
    OwnRevenueFuelCommission::factory()->create([
        'own_revenue_fuel_fund_id' => $fund->id,
        'status' => OwnRevenueFuelCommissionStatus::Confirmed,
        'created_by' => $actor->id,
        'confirmed_by' => $actor->id,
        'confirmed_at' => now()->subDays(2),
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(2),
    ]);
    OwnRevenueBudgetClosure::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'closed_by' => $actor->id,
        'closed_at' => now()->subDay(),
    ]);

    return compact('budget', 'actor');
}

test('audit timeline consolidates trusted events in descending order with operational language', function () {
    ['budget' => $budget, 'actor' => $actor] = auditTimelineFixture();

    $timeline = app(OwnRevenueAuditTimeline::class)->forBudget($budget, null);
    $dates = collect($timeline['events'])->pluck('occurred_at')->all();

    expect($timeline['applied_type'])->toBeNull()
        ->and($timeline['events'])->toHaveCount(8)
        ->and($dates)->toBe(collect($dates)->sortDesc()->values()->all())
        ->and($timeline['events'][0]['type'])->toBe('close')
        ->and($timeline['events'][0]['title'])->toBe('Ejercicio cerrado definitivamente')
        ->and(collect($timeline['events'])->pluck('actor_name')->filter()->unique()->all())
        ->toBe([$actor->name])
        ->and(collect($timeline['events'])->pluck('title')->implode(' '))
        ->not->toContain('own_revenue');
});

test('audit timeline filters by category and safely clears invalid filters', function () {
    ['budget' => $budget] = auditTimelineFixture();

    $fuel = app(OwnRevenueAuditTimeline::class)->forBudget($budget, 'fuel');
    $invalid = app(OwnRevenueAuditTimeline::class)->forBudget($budget, 'technical_table_name');

    expect($fuel['applied_type'])->toBe('fuel')
        ->and($fuel['events'])->toHaveCount(3)
        ->and(collect($fuel['events'])->pluck('type')->unique()->all())->toBe(['fuel'])
        ->and($invalid['applied_type'])->toBeNull()
        ->and($invalid['events'])->toHaveCount(8);
});

test('audit timeline is isolated to the requested annual budget', function () {
    ['budget' => $budget] = auditTimelineFixture();
    ['budget' => $otherBudget] = auditTimelineFixture();

    $timeline = app(OwnRevenueAuditTimeline::class)->forBudget($budget, null);

    expect(collect($timeline['events'])->pluck('id'))
        ->not->toContain('close:'.$otherBudget->annualClosure->id)
        ->and(collect($timeline['events'])->pluck('description')->implode(' '))
        ->not->toContain((string) $otherBudget->fiscal_year);
});
