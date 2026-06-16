<?php

use App\Actions\Finance\U300\StoreU300ImportedProject;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300VerdictUser(): User
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

function u300ProgramWithTwoActions(User $user): U300Program
{
    return app(StoreU300ImportedProject::class)->handle(
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
}

test('finance operator can capture federal verdict and the goal receives the authorized pool', function () {
    $user = u300VerdictUser();
    $program = u300ProgramWithTwoActions($user);
    $program->load('projects.goals.actions.requestedItems');
    $firstItem = $program->projects->first()->goals->first()->actions->first()->requestedItems->first();
    $secondItem = $program->projects->first()->goals->first()->actions->last()->requestedItems->first();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.verdict.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/verdict')
            ->where('program.id', $program->id)
            ->where('program.federal_authorized_total_cents', null)
            ->where('program.projects.0.goals.0.number', '5.1')
            ->where('program.projects.0.goals.0.actions.1.number', '5.1.2'));

    $this->actingAs($user)
        ->put(route('finance.u300.programs.verdict.update', $program), [
            'federal_authorized_total_cents' => 3600000,
            'items' => [
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
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.show', $program));

    $program->refresh()->load('projects.goals.actions.requestedItems');
    $goal = $program->projects->first()->goals->first();

    expect($program->approved_total_cents)->toBe(16100000)
        ->and($program->federal_authorized_total_cents)->toBe(3600000)
        ->and($goal->approved_total_cents)->toBe(16100000)
        ->and($goal->actions->first()->approved_total_cents)->toBe(11900000)
        ->and($goal->actions->last()->approved_total_cents)->toBe(4200000)
        ->and($goal->actions->last()->requestedItems->first()->approved_percentage)->toBe('51.85');

    $this->assertDatabaseHas('u300_requested_items', [
        'id' => $secondItem->id,
        'approved_amount_cents' => 4200000,
    ]);
    $this->assertDatabaseHas('u300_programs', [
        'id' => $program->id,
        'federal_authorized_total_cents' => 3600000,
    ]);
});
