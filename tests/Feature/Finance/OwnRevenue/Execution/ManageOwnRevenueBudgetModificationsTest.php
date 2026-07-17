<?php

use App\Actions\Finance\OwnRevenue\Execution\InitializeOwnRevenueModifiedBudget;
use App\Actions\Finance\OwnRevenue\Execution\StoreOwnRevenueBudgetModification;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueBudgetBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function ownRevenueExecutionUser(UserRole $role): User
{
    $email = sprintf('execution-%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/**
 * @return array{
 *     budget: OwnRevenueBudget,
 *     initialBudget: OwnRevenueInitialBudget,
 *     manager: User,
 *     officeSupplies: ExpenseClassification,
 *     printing: ExpenseClassification,
 *     electricity: ExpenseClassification
 * }
 */
function ownRevenueModificationFixture(): array
{
    $manager = ownRevenueExecutionUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InitialAuthorized,
        'fiscal_year' => 2026,
    ]);
    $proposal = OwnRevenueProposal::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'total_amount_cents' => 15_000,
    ]);
    $initialBudget = OwnRevenueInitialBudget::query()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_proposal_id' => $proposal->id,
        'total_amount_cents' => 15_000,
        'source_fingerprint' => str_repeat('a', 64),
        'authorization_fingerprint' => str_repeat('b', 64),
        'snapshot' => [
            'reconciliation' => [
                'groups' => [
                    ['specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '10000'],
                    ['specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '5000'],
                ],
            ],
        ],
        'authorized_by' => $manager->id,
        'authorized_at' => now(),
    ]);

    $classification = static fn (string $item, string $name, string $chapter, string $chapterName): ExpenseClassification => ExpenseClassification::query()->create([
        'fiscal_year' => 2026,
        'chapter_code' => $chapter,
        'chapter_name' => $chapterName,
        'concept_code' => substr($item, 0, 2).'000',
        'concept_name' => $chapterName,
        'generic_item_code' => substr($item, 0, 3).'00',
        'generic_item_name' => $name,
        'specific_item_code' => $item,
        'specific_item_name' => $name,
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);

    return [
        'budget' => $budget,
        'initialBudget' => $initialBudget,
        'manager' => $manager,
        'officeSupplies' => $classification('21101', 'Materiales y útiles de oficina', '2000', 'Materiales y suministros'),
        'printing' => $classification('21201', 'Materiales de impresión', '2000', 'Materiales y suministros'),
        'electricity' => $classification('31101', 'Energía eléctrica', '3000', 'Servicios generales'),
    ];
}

test('the authorized initial budget is materialized by item and month without duplicating lines', function () {
    ['initialBudget' => $initialBudget, 'officeSupplies' => $officeSupplies] = ownRevenueModificationFixture();

    $lines = app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget);
    app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget);

    expect($lines)->toHaveCount(1);
    $this->assertDatabaseCount('own_revenue_modified_budget_lines', 1);
    $this->assertDatabaseHas('own_revenue_modified_budget_lines', [
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'expense_classification_id' => $officeSupplies->id,
        'specific_item_code' => '21101',
        'month' => 5,
        'initial_amount_cents' => 15_000,
    ]);
});

test('a transfer moves available budget between items in the same chapter and month', function () {
    ['budget' => $budget, 'initialBudget' => $initialBudget, 'manager' => $manager, 'printing' => $printing] = ownRevenueModificationFixture();
    $source = app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget)->sole();

    $movement = app(StoreOwnRevenueBudgetModification::class)->handle($budget, $manager, [
        'type' => OwnRevenueBudgetModificationType::Transfer->value,
        'source_line_id' => $source->id,
        'destination_expense_classification_id' => $printing->id,
        'destination_month' => 5,
        'amount_cents' => 4_000,
        'reason' => 'Se requiere reforzar material de impresión.',
    ]);

    $destination = OwnRevenueModifiedBudgetLine::query()
        ->where('specific_item_code', '21201')
        ->where('month', 5)
        ->sole();
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($movement->type)->toBe(OwnRevenueBudgetModificationType::Transfer)
        ->and($movement->source_balance_before_cents)->toBe(15_000)
        ->and($movement->source_balance_after_cents)->toBe(11_000)
        ->and($movement->destination_balance_before_cents)->toBe(0)
        ->and($movement->destination_balance_after_cents)->toBe(4_000)
        ->and($balances->availableCents($source->fresh()))->toBe(11_000)
        ->and($balances->availableCents($destination))->toBe(4_000);
});

test('a recalendarization only moves the same item to a future month', function () {
    ['budget' => $budget, 'initialBudget' => $initialBudget, 'manager' => $manager, 'officeSupplies' => $officeSupplies] = ownRevenueModificationFixture();
    $source = app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget)->sole();

    app(StoreOwnRevenueBudgetModification::class)->handle($budget, $manager, [
        'type' => OwnRevenueBudgetModificationType::Rescheduling->value,
        'source_line_id' => $source->id,
        'destination_expense_classification_id' => $officeSupplies->id,
        'destination_month' => 8,
        'amount_cents' => 3_000,
        'reason' => 'La compra se realizará durante agosto.',
    ]);

    $destination = OwnRevenueModifiedBudgetLine::query()->where('specific_item_code', '21101')->where('month', 8)->sole();
    expect(app(OwnRevenueBudgetBalance::class)->availableCents($source->fresh()))->toBe(12_000)
        ->and(app(OwnRevenueBudgetBalance::class)->availableCents($destination))->toBe(3_000);
});

test('invalid or excessive modifications are rejected without changing balances', function (array $changes, string $field) {
    ['budget' => $budget, 'initialBudget' => $initialBudget, 'manager' => $manager, 'officeSupplies' => $officeSupplies, 'printing' => $printing, 'electricity' => $electricity] = ownRevenueModificationFixture();
    $source = app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget)->sole();
    $destination = match ($changes['destination']) {
        'printing' => $printing,
        'office' => $officeSupplies,
        default => $electricity,
    };

    try {
        app(StoreOwnRevenueBudgetModification::class)->handle($budget, $manager, [
            'type' => $changes['type'],
            'source_line_id' => $source->id,
            'destination_expense_classification_id' => $destination->id,
            'destination_month' => $changes['month'],
            'amount_cents' => $changes['amount'],
            'reason' => 'Movimiento inválido para comprobar reglas.',
        ]);
        $this->fail('The modification should have been rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(OwnRevenueBudgetModification::query()->count())->toBe(0)
        ->and(app(OwnRevenueBudgetBalance::class)->availableCents($source->fresh()))->toBe(15_000);
})->with([
    'transfer to another chapter' => [[
        'type' => 'transfer', 'destination' => 'electricity', 'month' => 5, 'amount' => 1_000,
    ], 'destination_expense_classification_id'],
    'transfer changing month' => [[
        'type' => 'transfer', 'destination' => 'printing', 'month' => 6, 'amount' => 1_000,
    ], 'destination_month'],
    'recalendarization changing item' => [[
        'type' => 'rescheduling', 'destination' => 'printing', 'month' => 6, 'amount' => 1_000,
    ], 'destination_expense_classification_id'],
    'recalendarization to prior month' => [[
        'type' => 'rescheduling', 'destination' => 'office', 'month' => 4, 'amount' => 1_000,
    ], 'destination_month'],
    'amount above available balance' => [[
        'type' => 'transfer', 'destination' => 'printing', 'month' => 5, 'amount' => 20_000,
    ], 'amount_cents'],
]);

test('only financial administrators may register budget modifications', function () {
    ['budget' => $budget, 'initialBudget' => $initialBudget, 'printing' => $printing] = ownRevenueModificationFixture();
    $source = app(InitializeOwnRevenueModifiedBudget::class)->handle($initialBudget)->sole();
    $auditor = ownRevenueExecutionUser(UserRole::FinanceAuditor);

    expect(fn () => app(StoreOwnRevenueBudgetModification::class)->handle($budget, $auditor, [
        'type' => OwnRevenueBudgetModificationType::Transfer->value,
        'source_line_id' => $source->id,
        'destination_expense_classification_id' => $printing->id,
        'destination_month' => 5,
        'amount_cents' => 1_000,
        'reason' => 'Movimiento no autorizado.',
    ]))->toThrow(AuthorizationException::class);
});
