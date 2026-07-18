<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Services\Finance\OwnRevenue\Closing\OwnRevenueAnnualCloseReview;

/** @return array{budget: OwnRevenueBudget, line: OwnRevenueModifiedBudgetLine, initialBudget: OwnRevenueInitialBudget} */
function annualCloseReviewFixture(): array
{
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InExecution,
    ]);
    $initialBudget = OwnRevenueInitialBudget::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'authorized_at' => now()->subMonth(),
    ]);
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'initial_amount_cents' => 100_000,
    ]);

    return compact('budget', 'line', 'initialBudget');
}

test('active expense dossiers block the annual close with operational language', function () {
    ['budget' => $budget, 'line' => $line] = annualCloseReviewFixture();
    OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::PurchaseInProgress,
    ]);

    $review = app(OwnRevenueAnnualCloseReview::class)->forBudget($budget);

    expect($review['eligible'])->toBeFalse()
        ->and($review['state_is_eligible'])->toBeTrue()
        ->and($review['blockers'])->toContainEqual([
            'type' => 'active_expense_dossiers',
            'count' => 1,
            'message' => 'Hay 1 expediente que todavía requiere concluirse.',
        ]);
});

test('pending requirements and fuel commissions block the annual close', function () {
    ['budget' => $budget, 'line' => $line] = annualCloseReviewFixture();
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::Paid,
    ]);
    OwnRevenueExpenseDossierRequirement::factory()->create([
        'own_revenue_expense_dossier_id' => $dossier->id,
        'status' => OwnRevenueExpenseRequirementStatus::Pending,
    ]);
    $fund = OwnRevenueFuelFund::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'source_expense_dossier_id' => $dossier->id,
    ]);
    OwnRevenueFuelCommission::factory()->create([
        'own_revenue_fuel_fund_id' => $fund->id,
        'status' => OwnRevenueFuelCommissionStatus::Pending,
    ]);

    $review = app(OwnRevenueAnnualCloseReview::class)->forBudget($budget);

    expect($review['eligible'])->toBeFalse()
        ->and($review['blockers'])->toContainEqual([
            'type' => 'pending_requirements',
            'count' => 1,
            'message' => 'Hay 1 requisito pendiente de atención.',
        ])->toContainEqual([
            'type' => 'pending_fuel_commissions',
            'count' => 1,
            'message' => 'Hay 1 comisión de combustible pendiente de confirmar.',
        ]);
});

test('terminal dossiers and remaining balances are captured without blocking close', function () {
    ['budget' => $budget, 'line' => $line, 'initialBudget' => $initialBudget] = annualCloseReviewFixture();
    foreach ([
        OwnRevenueExpenseDossierStatus::Paid,
        OwnRevenueExpenseDossierStatus::Rejected,
        OwnRevenueExpenseDossierStatus::Cancelled,
    ] as $status) {
        OwnRevenueExpenseDossier::factory()->create([
            'own_revenue_budget_id' => $budget->id,
            'own_revenue_modified_budget_line_id' => $line->id,
            'status' => $status,
            'amount_cents' => $status === OwnRevenueExpenseDossierStatus::Paid ? 25_000 : 5_000,
        ]);
    }
    $paidDossier = $budget->expenseDossiers()->where('status', OwnRevenueExpenseDossierStatus::Paid)->sole();
    $fund = OwnRevenueFuelFund::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'source_expense_dossier_id' => $paidDossier->id,
        'acquired_amount_cents' => 20_000,
    ]);
    OwnRevenueFuelCommission::factory()->create([
        'own_revenue_fuel_fund_id' => $fund->id,
        'status' => OwnRevenueFuelCommissionStatus::Confirmed,
        'amount_cents' => 5_000,
    ]);

    $review = app(OwnRevenueAnnualCloseReview::class)->forBudget($budget);

    expect($review['eligible'])->toBeTrue()
        ->and($review['blockers'])->toBe([])
        ->and($review['confirmation_phrase'])->toBe("CERRAR {$budget->fiscal_year}")
        ->and($review['snapshot']['balances']['available_amount_cents'])->toBe('75000')
        ->and($review['snapshot']['fuel']['available_amount_cents'])->toBe('15000')
        ->and($review['snapshot']['expense_dossiers']['by_status']['paid'])->toBe(1)
        ->and($review['snapshot']['initial_authorization']['id'])->toBe($initialBudget->id);
});

test('non executable states are ineligible and other budgets never affect review', function () {
    ['budget' => $budget] = annualCloseReviewFixture();
    $budget->update(['status' => OwnRevenueBudgetStatus::ProposalAdjusted]);
    ['budget' => $otherBudget, 'line' => $otherLine] = annualCloseReviewFixture();
    OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $otherBudget->id,
        'own_revenue_modified_budget_line_id' => $otherLine->id,
        'status' => OwnRevenueExpenseDossierStatus::Draft,
    ]);

    $review = app(OwnRevenueAnnualCloseReview::class)->forBudget($budget);

    expect($review['eligible'])->toBeFalse()
        ->and($review['state_is_eligible'])->toBeFalse()
        ->and($review['blockers'])->toBe([]);
});
