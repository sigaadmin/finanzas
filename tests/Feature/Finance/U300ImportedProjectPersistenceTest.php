<?php

use App\Actions\Finance\U300\StoreU300ImportedProject;
use App\Models\Finance\U300\U300Program;
use App\Models\User;

test('it persists a parsed U300 federal project as the original requested version', function () {
    $user = User::factory()->create();

    $program = app(StoreU300ImportedProject::class)->handle(
        importedBy: $user,
        fiscalYear: 2026,
        sourceFilename: 'Quintana Roo - CREN-FCP - Proyecto.pdf',
        sourcePath: 'u300/imports/project.pdf',
        parsed: [
            'general' => [
                'name' => '0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto',
                'objective' => 'Fortalecer de manera integral la capacidad académica.',
                'justification' => 'El Centro Regional enfrenta retos en infraestructura.',
                'requested_total_cents' => 3129682500,
            ],
            'responsible' => [
                'name' => 'William Miguel González Rodríguez',
                'position' => 'Director',
                'academic_degree' => 'Maestría',
                'phone' => '9838671071',
                'email' => 'direccion@crenfcp.edu.mx',
            ],
            'projects' => [
                [
                    'number' => '1',
                    'name' => 'Fortalecer las condiciones físicas y funcionales del CREN-FCP.',
                    'justification' => 'Atender prioridades institucionales.',
                    'goals' => [
                        [
                            'number' => '1.1',
                            'description' => 'Ampliar la capacidad instalada.',
                            'requested_total_cents' => 718300000,
                            'actions' => [
                                [
                                    'number' => '1.1.1',
                                    'name' => 'Conclusión y habilitación de dos aulas académicas',
                                    'justification' => 'Realizar obra civil necesaria.',
                                    'items' => [
                                        [
                                            'expense_concept' => 'Construcción',
                                            'expense_item' => 'Aulas',
                                            'period' => 4,
                                            'quantity' => 2,
                                            'unit_price_cents' => 230000000,
                                            'total_cents' => 460000000,
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

    expect($program)->toBeInstanceOf(U300Program::class)
        ->and($program->budgetVersions)->toHaveCount(1)
        ->and($program->budgetVersions->first()->kind)->toBe('original_requested')
        ->and($program->projects)->toHaveCount(1)
        ->and($program->projects->first()->goals)->toHaveCount(1)
        ->and($program->projects->first()->goals->first()->actions)->toHaveCount(1)
        ->and($program->projects->first()->goals->first()->actions->first()->requestedItems)->toHaveCount(1);

    $this->assertDatabaseHas('u300_programs', [
        'fiscal_year' => 2026,
        'name' => '0. Fortalecimiento Integral del CREN Felipe Carrillo Puerto',
        'requested_total_cents' => 3129682500,
        'responsible_email' => 'direccion@crenfcp.edu.mx',
    ]);

    $this->assertDatabaseHas('u300_requested_items', [
        'expense_concept' => 'Construcción',
        'expense_item' => 'Aulas',
        'period' => 4,
        'quantity' => 2,
        'unit_price_cents' => 230000000,
        'total_cents' => 460000000,
    ]);
});
