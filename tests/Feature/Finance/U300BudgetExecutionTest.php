<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300BudgetExecutionUser(): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    return $user;
}

function u300ProgramReadyForExecution(User $user): U300Program
{
    $classification = ExpenseClassification::create([
        'fiscal_year' => 2026,
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios Generales',
        'concept_code' => '3700',
        'concept_name' => 'Servicios de traslado y viáticos',
        'generic_item_code' => '3750',
        'generic_item_name' => 'Viáticos en el país',
        'specific_item_code' => '37501',
        'specific_item_name' => 'Viáticos en el país',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);

    $program = U300Program::create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => '0. Proyecto General U300',
        'objective' => 'Objetivo general.',
        'justification' => 'Justificación general.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
        'responsible_name' => 'William González',
        'responsible_position' => 'Director',
        'responsible_academic_degree' => 'Maestría',
        'responsible_phone' => '9838671071',
        'responsible_email' => 'direccion@crenfcp.edu.mx',
    ]);
    $version = $program->budgetVersions()->create([
        'created_by' => $user->id,
        'kind' => 'adjusted',
        'name' => 'Adecuación presupuestal',
        'status' => 'draft',
        'total_cents' => 16000000,
    ]);
    $project = $program->projects()->create([
        'number' => '5',
        'name' => 'Proyecto de evaluación institucional.',
        'justification' => 'Justificación.',
    ]);
    $goal = $project->goals()->create([
        'number' => '5.1',
        'description' => 'Meta con redistribución permitida.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
    ]);
    $action = $goal->actions()->create([
        'number' => '5.1.2',
        'name' => 'Acción concentradora',
        'justification' => 'Justificación dos.',
        'requested_total_cents' => 16000000,
        'approved_total_cents' => 16000000,
    ]);
    $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 16000000,
        'exercise_month' => 'OCT',
        'description' => 'Acción concentradora de la meta 5.1.',
        'justification' => 'Alimentos para movilidad académica.',
    ]);

    return $program;
}

test('finance operator can record budget execution movements and see available amounts', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.execution.index', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/execution')
            ->where('program.id', $program->id)
            ->where('program.lines.0.id', $line->id)
            ->where('program.lines.0.available_cents', 16000000)
            ->where('program.lines.0.executed_cents', 0));

    $this->actingAs($user)
        ->post(route('finance.u300.programs.execution.store', $program), [
            'u300_budget_line_id' => $line->id,
            'type' => 'expense',
            'movement_date' => '2026-10-19',
            'concept' => 'Anticipo de viáticos para movilidad académica.',
            'document_reference' => 'SOL-001',
            'amount_cents' => 4250000,
        ])
        ->assertRedirect(route('finance.u300.programs.execution.index', $program));

    $this->assertDatabaseHas('u300_budget_movements', [
        'u300_budget_line_id' => $line->id,
        'recorded_by' => $user->id,
        'type' => 'expense',
        'concept' => 'Anticipo de viáticos para movilidad académica.',
        'document_reference' => 'SOL-001',
        'amount_cents' => 4250000,
    ]);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.execution.index', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('program.lines.0.available_cents', 11750000)
            ->where('program.lines.0.executed_cents', 4250000)
            ->where('program.movements.0.concept', 'Anticipo de viáticos para movilidad académica.'));
});

test('budget execution cannot exceed the available amount', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.execution.store', $program), [
            'u300_budget_line_id' => $line->id,
            'type' => 'expense',
            'movement_date' => '2026-10-19',
            'concept' => 'Compra superior al disponible.',
            'document_reference' => 'SOL-002',
            'amount_cents' => 16000001,
        ])
        ->assertInvalid(['amount_cents']);

    $this->assertDatabaseMissing('u300_budget_movements', [
        'u300_budget_line_id' => $line->id,
        'concept' => 'Compra superior al disponible.',
    ]);
});

test('budget execution requires COG conversion before registering movements', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();
    $line->update(['expense_classification_id' => null]);

    $this->actingAs($user)
        ->post(route('finance.u300.programs.execution.store', $program), [
            'u300_budget_line_id' => $line->id,
            'type' => 'expense',
            'movement_date' => '2026-10-19',
            'concept' => 'Movimiento sin COG.',
            'document_reference' => 'SOL-004',
            'amount_cents' => 100000,
        ])
        ->assertInvalid(['u300_budget_line_id']);
});

test('budget execution must match the authorized exercise month', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.execution.store', $program), [
            'u300_budget_line_id' => $line->id,
            'type' => 'expense',
            'movement_date' => '2026-11-19',
            'concept' => 'Movimiento fuera de mes.',
            'document_reference' => 'SOL-005',
            'amount_cents' => 100000,
        ])
        ->assertInvalid(['movement_date']);
});

test('finance operator can cancel a budget movement without deleting its audit trail', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.execution.store', $program), [
            'u300_budget_line_id' => $line->id,
            'type' => 'expense',
            'movement_date' => '2026-10-19',
            'concept' => 'Anticipo de viáticos para movilidad académica.',
            'document_reference' => 'SOL-001',
            'amount_cents' => 4250000,
        ]);

    $movement = $line->movements()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('finance.u300.programs.execution.cancel', [$program, $movement]), [
            'cancellation_reason' => 'Captura duplicada por error.',
        ])
        ->assertRedirect(route('finance.u300.programs.execution.index', $program));

    $this->assertDatabaseHas('u300_budget_movements', [
        'id' => $movement->id,
        'cancelled_by' => $user->id,
        'cancellation_reason' => 'Captura duplicada por error.',
    ]);

    expect($movement->fresh()->cancelled_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.execution.index', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('program.lines.0.available_cents', 16000000)
            ->where('program.lines.0.executed_cents', 0)
            ->where('program.movements.0.is_cancelled', true)
            ->where('program.movements.0.cancellation_reason', 'Captura duplicada por error.'));
});

test('a cancelled budget movement cannot be cancelled again', function () {
    $user = u300BudgetExecutionUser();
    $program = u300ProgramReadyForExecution($user);
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();
    $movement = $line->movements()->create([
        'recorded_by' => $user->id,
        'type' => 'expense',
        'movement_date' => '2026-10-19',
        'concept' => 'Movimiento ya cancelado.',
        'document_reference' => 'SOL-003',
        'amount_cents' => 100000,
        'cancelled_at' => now(),
        'cancelled_by' => $user->id,
        'cancellation_reason' => 'Cancelación previa.',
    ]);

    $this->actingAs($user)
        ->patch(route('finance.u300.programs.execution.cancel', [$program, $movement]), [
            'cancellation_reason' => 'Segundo intento.',
        ])
        ->assertInvalid(['movement']);
});
