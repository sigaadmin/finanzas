<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Reports\OwnRevenueInternalReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{budget: OwnRevenueBudget, materialLine: OwnRevenueModifiedBudgetLine, serviceLine: OwnRevenueModifiedBudgetLine} */
function internalReportFixture(): array
{
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2026]);
    $materialLine = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales de oficina',
        'month' => 5,
        'initial_amount_cents' => 60_000,
    ]);
    $serviceLine = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $materialLine->own_revenue_budget_id,
        'own_revenue_initial_budget_id' => $materialLine->own_revenue_initial_budget_id,
        'expense_classification_id' => $materialLine->expense_classification_id,
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios generales',
        'specific_item_code' => '33901',
        'specific_item_name' => 'Servicios profesionales',
        'month' => 6,
        'initial_amount_cents' => 40_000,
    ]);

    foreach ([
        [OwnRevenueExpenseDossierStatus::SufficiencyRequested, 10_000],
        [OwnRevenueExpenseDossierStatus::SufficiencyConfirmed, 20_000],
        [OwnRevenueExpenseDossierStatus::Paid, 30_000],
    ] as [$status, $amount]) {
        OwnRevenueExpenseDossier::factory()->create([
            'own_revenue_budget_id' => $materialLine->own_revenue_budget_id,
            'own_revenue_modified_budget_line_id' => $materialLine->id,
            'status' => $status,
            'amount_cents' => $amount,
        ]);
    }

    foreach ([OwnRevenueExpenseDossierStatus::Cancelled, OwnRevenueExpenseDossierStatus::Rejected] as $status) {
        OwnRevenueExpenseDossier::factory()->create([
            'own_revenue_budget_id' => $serviceLine->own_revenue_budget_id,
            'own_revenue_modified_budget_line_id' => $serviceLine->id,
            'status' => $status,
            'amount_cents' => 5_000,
        ]);
    }

    return [
        'budget' => $materialLine->budget,
        'materialLine' => $materialLine,
        'serviceLine' => $serviceLine,
    ];
}

test('internal reports calculate budget balances and planning progress', function () {
    ['budget' => $budget] = internalReportFixture();

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, []);

    expect($data['summary'])->toBe([
        'initial_amount_cents' => '100000',
        'modified_amount_cents' => '100000',
        'reserved_amount_cents' => '10000',
        'committed_amount_cents' => '20000',
        'paid_amount_cents' => '30000',
        'available_amount_cents' => '40000',
    ])->and($data['planning_vs_execution'])->toBe([
        'planned_amount_cents' => '100000',
        'paid_amount_cents' => '30000',
        'difference_amount_cents' => '70000',
        'execution_percentage' => '30.00',
    ])->and($data['lines'])->toHaveCount(2);
});

test('internal reports include the planning and adjustment comparison', function () {
    ['budget' => $budget] = internalReportFixture();
    $budget->proposals()->firstOrFail()->update(['total_amount_cents' => 100_000]);

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, []);

    expect($data['planning_adjustments']['version_count'])->toBe(1)
        ->and($data['planning_adjustments']['versions'][0]['total_amount_cents'])->toBe('100000');
});

test('internal reports isolate budgets and preserve portable integer amounts', function () {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2026]);
    OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'initial_amount_cents' => 9_007_199_254_740_993,
    ]);
    $otherBudget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);
    OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $otherBudget->id,
        'initial_amount_cents' => 8_000,
    ]);

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, []);

    expect($data['summary']['initial_amount_cents'])->toBe('9007199254740993')
        ->and($data['planning_vs_execution']['planned_amount_cents'])->toBe('9007199254740993')
        ->and($data['lines'][0]['initial_amount_cents'])->toBe('9007199254740993');
});

test('internal reports apply valid filters and summarize related operations', function () {
    ['budget' => $budget, 'materialLine' => $materialLine, 'serviceLine' => $serviceLine] = internalReportFixture();
    OwnRevenueBudgetModification::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'source_line_id' => $materialLine->id,
        'destination_line_id' => $serviceLine->id,
        'type' => OwnRevenueBudgetModificationType::Transfer,
        'amount_cents' => 5_000,
    ]);
    $paidDossier = $materialLine->expenseDossiers()
        ->where('status', OwnRevenueExpenseDossierStatus::Paid)
        ->sole();
    OwnRevenueExpenseDossierRequirement::factory()->count(2)->create([
        'own_revenue_expense_dossier_id' => $paidDossier->id,
    ]);

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, [
        'chapter_code' => '2000',
        'specific_item_code' => '21101',
        'month' => 5,
    ]);

    expect($data['filters']['applied'])->toBe([
        'chapter_code' => '2000',
        'specific_item_code' => '21101',
        'month' => 5,
    ])->and($data['lines'])->toHaveCount(1)
        ->and($data['modifications']['total'])->toBe(1)
        ->and($data['modifications']['transfer_amount_cents'])->toBe('5000')
        ->and($data['expense_dossiers']['by_status']['paid'])->toBe(1)
        ->and($data['expense_dossiers']['pending_requirements'])->toBe(2);
});

test('internal reports safely clear filters that do not exist in the budget', function () {
    ['budget' => $budget] = internalReportFixture();

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, [
        'chapter_code' => '9000',
        'specific_item_code' => '99999',
        'month' => 13,
    ]);

    expect($data['filters']['applied'])->toBe([
        'chapter_code' => null,
        'specific_item_code' => null,
        'month' => null,
    ])->and($data['lines'])->toHaveCount(2);
});

test('internal reports clear a selected item that does not belong to the selected chapter', function () {
    ['budget' => $budget] = internalReportFixture();

    $data = app(OwnRevenueInternalReportData::class)->forBudget($budget, [
        'chapter_code' => '2000',
        'specific_item_code' => '33901',
    ]);

    expect($data['filters']['applied'])->toBe([
        'chapter_code' => '2000',
        'specific_item_code' => null,
        'month' => null,
    ])->and($data['lines'])->toHaveCount(1)
        ->and($data['lines'][0]['specific_item_code'])->toBe('21101');
});
