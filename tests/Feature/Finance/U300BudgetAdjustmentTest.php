<?php

use App\Actions\Finance\U300\StoreU300ImportedProject;
use App\Actions\Finance\U300\UpdateU300FederalVerdict;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300AdjustmentUser(): User
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

function u300ProgramReadyForAdjustment(User $user): U300Program
{
    $program = app(StoreU300ImportedProject::class)->handle(
        importedBy: $user,
        fiscalYear: 2026,
        sourceFilename: 'proyecto.pdf',
        sourcePath: 'u300/imports/proyecto.pdf',
        parsed: [
            'general' => [
                'name' => '0. Proyecto General U300',
                'objective' => 'Objetivo general.',
                'justification' => 'Justificación general.',
                'requested_total_cents' => 20000000,
            ],
            'responsible' => [
                'name' => 'William González',
                'position' => 'Director',
                'academic_degree' => 'Maestría',
                'phone' => '9838671071',
                'email' => 'direccion@crenfcp.edu.mx',
            ],
            'projects' => [
                [
                    'number' => '5',
                    'name' => 'Proyecto de evaluación institucional.',
                    'justification' => 'Justificación.',
                    'goals' => [
                        [
                            'number' => '5.1',
                            'description' => 'Meta con redistribución permitida.',
                            'requested_total_cents' => 20000000,
                            'actions' => [
                                [
                                    'number' => '5.1.1',
                                    'name' => 'Acción uno',
                                    'justification' => 'Justificación uno.',
                                    'items' => [
                                        [
                                            'expense_concept' => 'Servicios',
                                            'expense_item' => 'Evaluación',
                                            'period' => 4,
                                            'quantity' => 1,
                                            'unit_price_cents' => 11900000,
                                            'total_cents' => 11900000,
                                        ],
                                    ],
                                ],
                                [
                                    'number' => '5.1.2',
                                    'name' => 'Acción concentradora',
                                    'justification' => 'Justificación dos.',
                                    'items' => [
                                        [
                                            'expense_concept' => 'Servicios',
                                            'expense_item' => 'Capacitación',
                                            'period' => 4,
                                            'quantity' => 1,
                                            'unit_price_cents' => 8100000,
                                            'total_cents' => 8100000,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    );

    $program->load('projects.goals.actions.requestedItems');
    $firstItem = $program->projects->first()->goals->first()->actions->first()->requestedItems->first();
    $secondItem = $program->projects->first()->goals->first()->actions->last()->requestedItems->first();

    return app(UpdateU300FederalVerdict::class)->handle($program, [
        [
            'id' => $firstItem->id,
            'approved_amount_cents' => 11900000,
            'approved_percentage' => 100,
        ],
        [
            'id' => $secondItem->id,
            'approved_amount_cents' => 4200000,
            'approved_percentage' => 51.85,
        ],
    ]);
}

test('finance operator can redistribute the authorized goal pool into one adjusted action', function () {
    $user = u300AdjustmentUser();
    $program = u300ProgramReadyForAdjustment($user);
    $program->load('projects.goals.actions');
    $goal = $program->projects->first()->goals->first();
    $firstAction = $goal->actions->first();
    $secondAction = $goal->actions->last();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.adjustment.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/adjustment')
            ->where('program.id', $program->id)
            ->where('program.projects.0.goals.0.approved_total_cents', 16100000));

    $this->actingAs($user)
        ->put(route('finance.u300.programs.adjustment.update', $program), [
            'allocations' => [
                [
                    'u300_action_id' => $firstAction->id,
                    'amount_cents' => 0,
                ],
                [
                    'u300_action_id' => $secondAction->id,
                    'amount_cents' => 16000000,
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.show', $program));

    $this->assertDatabaseHas('u300_budget_versions', [
        'u300_program_id' => $program->id,
        'kind' => 'adjusted',
        'total_cents' => 16000000,
    ]);

    $this->assertDatabaseHas('u300_budget_lines', [
        'u300_action_id' => $secondAction->id,
        'amount_cents' => 16000000,
        'description' => '5.1.2 Acción concentradora',
    ]);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.adjustment.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('program.projects.0.goals.0.actions', 2)
            ->where('program.projects.0.goals.0.actions.0.id', $firstAction->id)
            ->where('program.projects.0.goals.0.actions.0.adjusted_amount_cents', 0)
            ->where('program.projects.0.goals.0.actions.1.id', $secondAction->id)
            ->where('program.projects.0.goals.0.actions.1.adjusted_amount_cents', 16000000));
});

test('adjusted allocations cannot exceed the authorized pool for each goal', function () {
    $user = u300AdjustmentUser();
    $program = u300ProgramReadyForAdjustment($user);
    $program->load('projects.goals.actions');
    $secondAction = $program->projects->first()->goals->first()->actions->last();

    $this->actingAs($user)
        ->from(route('finance.u300.programs.adjustment.edit', $program))
        ->put(route('finance.u300.programs.adjustment.update', $program), [
            'allocations' => [
                [
                    'u300_action_id' => $secondAction->id,
                    'amount_cents' => 16200000,
                    'description' => 'Rebase de prueba.',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.adjustment.edit', $program))
        ->assertSessionHasErrors('allocations');
});

test('adjusted allocations cannot exceed the final federal authorized amount', function () {
    $user = u300AdjustmentUser();
    $program = u300ProgramReadyForAdjustment($user);
    $program->update([
        'federal_authorized_total_cents' => 3600000,
    ]);
    $program->load('projects.goals.actions');
    $secondAction = $program->projects->first()->goals->first()->actions->last();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.adjustment.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/adjustment')
            ->where('program.federal_authorized_total_cents', 3600000)
            ->where('program.adjustment_limit_cents', 3600000));

    $this->actingAs($user)
        ->from(route('finance.u300.programs.adjustment.edit', $program))
        ->put(route('finance.u300.programs.adjustment.update', $program), [
            'allocations' => [
                [
                    'u300_action_id' => $secondAction->id,
                    'amount_cents' => 4000000,
                    'description' => 'Rebase del monto federal final.',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.adjustment.edit', $program))
        ->assertSessionHasErrors('allocations');
});

test('adjustment cannot be changed after active budget movements exist', function () {
    $user = u300AdjustmentUser();
    $program = u300ProgramReadyForAdjustment($user);
    $program->load('projects.goals.actions');
    $secondAction = $program->projects->first()->goals->first()->actions->last();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.adjustment.update', $program), [
            'allocations' => [
                [
                    'u300_action_id' => $secondAction->id,
                    'amount_cents' => 16000000,
                    'description' => 'Acción concentradora de la meta 5.1.',
                ],
            ],
        ]);

    $program->refresh()->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();
    $line->movements()->create([
        'recorded_by' => $user->id,
        'type' => 'expense',
        'movement_date' => '2026-10-19',
        'concept' => 'Anticipo de viáticos.',
        'document_reference' => 'SOL-001',
        'amount_cents' => 100000,
    ]);

    $this->actingAs($user)
        ->from(route('finance.u300.programs.adjustment.edit', $program))
        ->put(route('finance.u300.programs.adjustment.update', $program), [
            'allocations' => [
                [
                    'u300_action_id' => $secondAction->id,
                    'amount_cents' => 15000000,
                    'description' => 'Cambio posterior al ejercicio.',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.adjustment.edit', $program))
        ->assertSessionHasErrors('allocations');

    $this->assertDatabaseHas('u300_budget_lines', [
        'id' => $line->id,
        'amount_cents' => 16000000,
        'description' => 'Acción concentradora de la meta 5.1.',
    ]);
});
