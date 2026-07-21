<?php

use App\Actions\Finance\U300\UpdateU300TechnicalSheets;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300TechnicalSheetUser(): User
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

function u300ProgramWithCogLine(User $user): U300Program
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
        'exercise_month' => 'AGO',
        'description' => 'Acción concentradora de la meta 5.1.',
        'justification' => 'Alimentos para movilidad académica.',
    ]);
    $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 5000000,
        'exercise_month' => 'DIC',
        'description' => 'Segunda partida con presupuesto asignado.',
        'justification' => 'Material complementario.',
        'sort_order' => 2,
    ]);
    $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 0,
        'exercise_month' => 'NOV',
        'description' => 'Partida sin presupuesto asignado.',
        'justification' => 'No debe capturar ficha técnica.',
        'sort_order' => 3,
    ]);

    return $program;
}

function u300ProgramWithMaterialsLine(User $user): U300Program
{
    $classification = ExpenseClassification::create([
        'fiscal_year' => 2026,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '2110',
        'generic_item_name' => 'Materiales, útiles y equipos menores de oficina',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales y útiles de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);

    $program = U300Program::create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => '0. Proyecto General U300',
        'objective' => 'Objetivo general.',
        'justification' => 'Justificación general.',
        'requested_total_cents' => 7500000,
        'approved_total_cents' => 7500000,
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
        'total_cents' => 7500000,
    ]);
    $project = $program->projects()->create([
        'number' => '6',
        'name' => 'Proyecto de equipamiento académico.',
        'justification' => 'Justificación.',
    ]);
    $goal = $project->goals()->create([
        'number' => '6.1',
        'description' => 'Meta de materiales.',
        'requested_total_cents' => 7500000,
        'approved_total_cents' => 7500000,
    ]);
    $action = $goal->actions()->create([
        'number' => '6.1.1',
        'name' => 'Adquisición de materiales didácticos',
        'justification' => 'Materiales para prácticas.',
        'requested_total_cents' => 7500000,
        'approved_total_cents' => 7500000,
    ]);
    $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 7500000,
        'exercise_month' => 'SEP',
        'description' => 'Materiales para prácticas académicas.',
        'justification' => 'Materiales de oficina.',
    ]);

    return $program;
}

test('finance operator can capture technical sheets for adjusted COG lines', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithCogLine($user);
    $lines = $program->budgetVersions()->first()->budgetLines()->where('amount_cents', '>', 0)->orderBy('sort_order')->get();
    $line = $lines->first();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/technical-sheets')
            ->where('program.id', $program->id)
            ->has('program.lines', 2)
            ->where('program.lines.0.id', $line->id)
            ->where('program.lines.0.cog_code', '37501')
            ->where('program.lines.0.default_scheduled_date', 'Agosto de 2026')
            ->where('program.lines.0.action_justification', 'Justificación dos.')
            ->where('program.lines.0.sheet', null)
            ->where('program.shared_sheet_fields.delivery_location', null)
            ->where('program.shared_sheet_fields.supervisor', null)
            ->where('program.shared_sheet_fields.payment_terms', null));

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'item_name' => 'Servicio de alimentos para movilidad académica',
                    'objective' => 'Propiciar movilidad académica.',
                    'work_description' => 'Comprar alimentos para estudiantes.',
                    'technical_specs' => 'Alimentos para 3 estudiantes por 8 semanas.',
                    'beneficiaries' => '3 estudiantes',
                    'scheduled_date' => '19 de octubre al 13 de diciembre de 2026',
                    'deliverables' => 'Informe con evidencia fotográfica.',
                    'delivery_location' => 'Servicios Educativos de Quintana Roo.',
                    'supervisor' => 'Dra. Geraldine Díaz Argáez',
                    'payment_terms' => 'En una sola emisión, a través de transferencia electrónica.',
                ],
                [
                    'u300_budget_line_id' => $lines->last()->id,
                    'item_name' => 'Material didáctico para movilidad académica',
                    'objective' => 'Propiciar movilidad académica con materiales.',
                    'work_description' => 'Comprar materiales para estudiantes.',
                    'technical_specs' => 'Materiales didácticos para movilidad.',
                    'beneficiaries' => '3 estudiantes',
                    'scheduled_date' => 'Diciembre de 2026',
                    'deliverables' => 'Informe con evidencia documental.',
                    'delivery_location' => 'Servicios Educativos de Quintana Roo.',
                    'supervisor' => 'Dra. Geraldine Díaz Argáez',
                    'payment_terms' => 'En una sola emisión, a través de transferencia electrónica.',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.show', $program));

    $this->assertDatabaseHas('u300_technical_sheets', [
        'u300_budget_line_id' => $line->id,
        'item_name' => 'Servicio de alimentos para movilidad académica',
        'objective' => 'Propiciar movilidad académica.',
        'beneficiaries' => '3 estudiantes',
        'payment_terms' => 'En una sola emisión, a través de transferencia electrónica.',
    ]);
    $this->assertDatabaseHas('u300_technical_sheets', [
        'u300_budget_line_id' => $lines->last()->id,
        'item_name' => 'Material didáctico para movilidad académica',
        'delivery_location' => 'Servicios Educativos de Quintana Roo.',
        'supervisor' => 'Dra. Geraldine Díaz Argáez',
        'payment_terms' => 'En una sola emisión, a través de transferencia electrónica.',
    ]);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('program.lines.0.sheet.item_name', 'Servicio de alimentos para movilidad académica')
            ->where('program.shared_sheet_fields.delivery_location', 'Servicios Educativos de Quintana Roo.')
            ->where('program.shared_sheet_fields.supervisor', 'Dra. Geraldine Díaz Argáez')
            ->where('program.shared_sheet_fields.payment_terms', 'En una sola emisión, a través de transferencia electrónica.'));
});

test('finance operator can save modal technical sheet data without leaving capture screen', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithCogLine($user);
    $lines = $program->budgetVersions()->first()->budgetLines()->where('amount_cents', '>', 0)->orderBy('sort_order')->get();
    $line = $lines->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'stay_on_page' => true,
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'item_name' => 'Servicio integral para acción agrupada',
                    'objective' => 'Guardar datos generales desde modal.',
                    'work_description' => null,
                    'technical_specs' => null,
                    'beneficiaries' => null,
                    'scheduled_date' => 'Agosto de 2026',
                    'deliverables' => null,
                    'delivery_location' => 'Área de entrega institucional.',
                    'supervisor' => 'Responsable de seguimiento.',
                    'payment_terms' => 'Pago contra entrega.',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.technical-sheets.edit', $program));

    $this->assertDatabaseHas('u300_technical_sheets', [
        'u300_budget_line_id' => $line->id,
        'item_name' => 'Servicio integral para acción agrupada',
        'objective' => 'Guardar datos generales desde modal.',
        'delivery_location' => 'Área de entrega institucional.',
        'supervisor' => 'Responsable de seguimiento.',
        'payment_terms' => 'Pago contra entrega.',
    ]);
});

test('finance operator can open a dedicated capture screen for a materials budget line', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.lines.edit', [$program, $line]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/technical-sheet-line')
            ->where('program.id', $program->id)
            ->where('line.id', $line->id)
            ->where('line.action_number', '6.1.1')
            ->where('line.action_name', 'Adquisición de materiales didácticos')
            ->where('line.cog_code', '21101')
            ->where('line.cog_name', 'Materiales y útiles de oficina')
            ->where('line.chapter_code', '2000')
            ->where('line.uses_goods_list', true)
            ->where('line.default_scheduled_date', 'Septiembre de 2026')
            ->has('action_lines', 1)
            ->where('action_lines.0.id', $line->id)
            ->where('action_lines.0.cog_code', '21101')
            ->where('action_lines.0.cog_name', 'Materiales y útiles de oficina')
            ->where('action_lines.0.amount_cents', 7500000));
});

test('dedicated line capture includes other positive budget lines from the same action', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithCogLine($user);
    $lines = $program->budgetVersions()->first()->budgetLines()->where('amount_cents', '>', 0)->orderBy('sort_order')->get();
    $line = $lines->first();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.lines.edit', [$program, $line]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/technical-sheet-line')
            ->has('action_lines', 2)
            ->where('action_lines.0.id', $line->id)
            ->where('action_lines.0.cog_code', '37501')
            ->where('action_lines.0.cog_name', 'Viáticos en el país')
            ->where('action_lines.0.amount_cents', 16000000)
            ->where('action_lines.0.is_current', true)
            ->where('action_lines.1.id', $lines->last()->id)
            ->where('action_lines.1.amount_cents', 5000000)
            ->where('action_lines.1.is_current', false));
});

test('finance operator can save a dedicated line capture and remain on that screen', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'stay_on_page' => true,
            'return_to_line_id' => $line->id,
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'item_name' => 'Materiales didácticos',
                    'objective' => 'Apoyar prácticas académicas.',
                    'work_description' => 'Adquirir materiales.',
                    'technical_specs' => '1. Caja de hojas\nUnidad de medida: Caja\nCantidad mínima: 3',
                    'beneficiaries' => '120 estudiantes',
                    'scheduled_date' => 'Septiembre de 2026',
                    'deliverables' => 'Evidencia de recepción.',
                    'delivery_location' => null,
                    'supervisor' => null,
                    'payment_terms' => null,
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.technical-sheets.lines.edit', [$program, $line]));

    $this->assertDatabaseHas('u300_technical_sheets', [
        'u300_budget_line_id' => $line->id,
        'technical_specs' => '1. Caja de hojas\nUnidad de medida: Caja\nCantidad mínima: 3',
    ]);
});

test('finance operator can save requested goods with a reference photo', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'stay_on_page' => true,
            'return_to_line_id' => $line->id,
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'item_name' => 'Materiales didácticos',
                    'objective' => 'Apoyar prácticas académicas.',
                    'work_description' => 'Adquirir materiales.',
                    'beneficiaries' => '120 estudiantes',
                    'scheduled_date' => 'Septiembre de 2026',
                    'deliverables' => 'Evidencia de recepción.',
                    'delivery_location' => null,
                    'supervisor' => null,
                    'payment_terms' => null,
                    'goods' => [
                        [
                            'unit' => 'Pieza',
                            'description' => 'Microscopio escolar',
                            'minimum_quantity' => '2',
                            'unit_price' => '1500',
                            'specifications' => "*Características del bien*\n- Lente _óptico_\n- Iluminación *LED*",
                            'reference_photo' => UploadedFile::fake()->image('microscopio.jpg'),
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.technical-sheets.lines.edit', [$program, $line]));

    $storedReferencePhotos = Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos');

    expect($storedReferencePhotos)->toHaveCount(1);

    $sheet = $line->technicalSheet()->first();

    expect($sheet?->technical_specs)->toBeNull();
    expect($sheet?->goods_profile)
        ->toHaveCount(1)
        ->and($sheet?->goods_profile[0]['description'])->toBe('Microscopio escolar')
        ->and($sheet?->goods_profile[0]['minimum_quantity'])->toBe('2')
        ->and($sheet?->goods_profile[0]['unit_price'])->toBe('1500')
        ->and($sheet?->goods_profile[0]['specifications'])
        ->toBe("*Características del bien*\n- Lente _óptico_\n- Iluminación *LED*")
        ->and($sheet?->goods_profile[0]['reference_photo_path'])
        ->toStartWith('storage/u300/technical-sheets/reference-photos/');
});

test('finance operator can save a detailed legacy-sized goods profile', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $detailedSpecifications = str_repeat('Especificación técnica detallada. ', 80);

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'stay_on_page' => true,
            'return_to_line_id' => $line->id,
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => [
                        [
                            'description' => 'Equipo de red principal',
                            'specifications' => $detailedSpecifications,
                        ],
                        [
                            'description' => 'Equipo de red secundario',
                            'specifications' => $detailedSpecifications,
                        ],
                    ],
                ],
            ],
        ]);

    expect($line->technicalSheet()->first()?->goods_profile)
        ->toBeArray()
        ->toHaveCount(2);
});

test('finance operator cannot persist an arbitrary requested good reference photo path', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'stay_on_page' => true,
            'return_to_line_id' => $line->id,
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => [
                        [
                            'description' => 'Microscopio escolar',
                            'reference_photo_path' => '/tmp/private-reference.jpg',
                        ],
                    ],
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods.0.reference_photo_path');
});

test('finance operator cannot upload a requested good image format omitted by the Word exporter', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => [
                        [
                            'description' => 'Microscopio escolar',
                            'reference_photo' => UploadedFile::fake()->image('microscopio.gif'),
                        ],
                    ],
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods.0.reference_photo');

    expect(Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos'))->toBeEmpty();
});

test('requested goods profile cannot exceed the technical specifications limit', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => collect(range(1, 17))
                        ->map(fn (int $index): array => [
                            'description' => 'Bien '.$index,
                            'specifications' => str_repeat('a', 3000),
                        ])
                        ->all(),
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods');
});

test('requested goods list has a bounded number of entries', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => collect(range(1, 51))
                        ->map(fn (int $index): array => ['description' => 'Bien '.$index])
                        ->all(),
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods');
});

test('requested goods preserve paragraph breaks when the capture screen is reopened', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $specifications = "Primer párrafo.\n\nSegundo párrafo.";

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => [
                        [
                            'unit' => 'Pieza',
                            'description' => 'Microscopio escolar',
                            'minimum_quantity' => '2',
                            'unit_price' => '1500',
                            'specifications' => $specifications,
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.lines.edit', [$program, $line]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('line.goods.0.description', 'Microscopio escolar')
            ->where('line.goods.0.specifications', $specifications));
});

test('replacing a requested good reference photo removes the superseded file', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $payload = fn (UploadedFile $photo): array => [
        '_method' => 'put',
        'sheets' => [
            [
                'u300_budget_line_id' => $line->id,
                'goods' => [
                    [
                        'description' => 'Microscopio escolar',
                        'specifications' => 'Lente óptico.',
                        'reference_photo' => $photo,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(
            route('finance.u300.programs.technical-sheets.update', $program),
            $payload(UploadedFile::fake()->image('primera.jpg')),
        )
        ->assertRedirect();

    $firstPhoto = Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos')[0];

    $this->actingAs($user)
        ->post(
            route('finance.u300.programs.technical-sheets.update', $program),
            $payload(UploadedFile::fake()->image('segunda.jpg')),
        )
        ->assertRedirect();

    expect(Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos'))->toHaveCount(1);
    Storage::disk('public')->assertMissing($firstPhoto);
});

test('technical sheet update rejects lines from another program before storing photos', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $otherProgram = u300ProgramWithCogLine($user);
    $otherLine = $otherProgram->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'sheets' => [
                [
                    'u300_budget_line_id' => $otherLine->id,
                    'goods' => [
                        [
                            'description' => 'Microscopio escolar',
                            'reference_photo' => UploadedFile::fake()->image('microscopio.jpg'),
                        ],
                    ],
                ],
            ],
        ])
        ->assertInvalid('sheets.0.u300_budget_line_id');

    expect(Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos'))->toBeEmpty();
});

test('saving defaults and shared delivery data does not create uncaptured technical sheets', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithCogLine($user);
    $lines = $program->budgetVersions()->first()->budgetLines()->where('amount_cents', '>', 0)->get();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'stay_on_page' => true,
            'sheets' => $lines->map(fn ($line): array => [
                'u300_budget_line_id' => $line->id,
                'item_name' => $line->description,
                'objective' => $line->action->justification,
                'scheduled_date' => 'Agosto de 2026',
                'delivery_location' => 'Área de entrega institucional.',
                'supervisor' => 'Responsable de seguimiento.',
                'payment_terms' => 'Pago contra entrega.',
            ])->all(),
        ])
        ->assertRedirect(route('finance.u300.programs.technical-sheets.edit', $program));

    expect($program->budgetVersions()->first()->budgetLines()->has('technicalSheet')->count())->toBe(0);
});

test('technical sheet update rejects duplicate budget line ids', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                ['u300_budget_line_id' => $line->id, 'work_description' => 'Primera captura.'],
                ['u300_budget_line_id' => $line->id, 'work_description' => 'Segunda captura.'],
            ],
        ])
        ->assertInvalid('sheets.1.u300_budget_line_id');
});

test('technical sheet update returns validation errors for malformed goods', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => ['invalid-good'],
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods.0');
});

test('technical sheet update rejects a photo path owned by another budget line', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $version = $program->budgetVersions()->first();
    $firstLine = $version->budgetLines()->first();
    $secondLine = $version->budgetLines()->create([
        'u300_action_id' => $firstLine->u300_action_id,
        'expense_classification_id' => $firstLine->expense_classification_id,
        'amount_cents' => 500000,
        'exercise_month' => 'SEP',
        'description' => 'Segunda partida de materiales.',
        'justification' => 'Materiales adicionales.',
    ]);

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'sheets' => [
                [
                    'u300_budget_line_id' => $firstLine->id,
                    'goods' => [
                        [
                            'description' => 'Microscopio escolar',
                            'reference_photo' => UploadedFile::fake()->image('microscopio.jpg'),
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $photoPath = $firstLine->technicalSheet()->first()->goods_profile[0]['reference_photo_path'];

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $secondLine->id,
                    'goods' => [
                        [
                            'description' => 'Proyector escolar',
                            'reference_photo_path' => $photoPath,
                        ],
                    ],
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods.0.reference_photo_path');
});

test('removing a shared legacy photo reference keeps the file used by another sheet', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $version = $program->budgetVersions()->first();
    $firstLine = $version->budgetLines()->first();
    $secondLine = $version->budgetLines()->create([
        'u300_action_id' => $firstLine->u300_action_id,
        'expense_classification_id' => $firstLine->expense_classification_id,
        'amount_cents' => 500000,
        'exercise_month' => 'SEP',
        'description' => 'Segunda partida de materiales.',
        'justification' => 'Materiales adicionales.',
    ]);
    $relativePhotoPath = 'u300/technical-sheets/reference-photos/shared.jpg';
    $photoPath = 'storage/'.$relativePhotoPath;
    Storage::disk('public')->put($relativePhotoPath, 'image contents');

    foreach ([$firstLine, $secondLine] as $line) {
        $line->technicalSheet()->create([
            'item_name' => 'Bien compartido',
            'goods_profile' => [[
                'description' => 'Microscopio escolar',
                'reference_photo_path' => $photoPath,
            ]],
        ]);
    }

    $this->actingAs($user)
        ->put(route('finance.u300.programs.technical-sheets.update', $program), [
            'sheets' => [
                [
                    'u300_budget_line_id' => $firstLine->id,
                    'goods' => [],
                ],
            ],
        ])
        ->assertRedirect();

    Storage::disk('public')->assertExists($relativePhotoPath);
});

test('requested goods profile accounts for persisted photo path lengths', function () {
    Storage::fake('public');

    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $goods = collect(range(1, 50))
        ->map(fn (int $index): array => [
            'description' => 'Bien '.$index,
            'specifications' => str_repeat('a', 1000),
            'reference_photo' => UploadedFile::fake()->image("bien-{$index}.jpg", 10, 10),
        ])
        ->all();

    $this->actingAs($user)
        ->post(route('finance.u300.programs.technical-sheets.update', $program), [
            '_method' => 'put',
            'sheets' => [
                [
                    'u300_budget_line_id' => $line->id,
                    'goods' => $goods,
                ],
            ],
        ])
        ->assertInvalid('sheets.0.goods');

    expect(Storage::disk('public')->allFiles('u300/technical-sheets/reference-photos'))->toBeEmpty();
});

test('technical sheet action rejects a stale photo path after acquiring the line lock', function () {
    $user = u300TechnicalSheetUser();
    $program = u300ProgramWithMaterialsLine($user);
    $line = $program->budgetVersions()->first()->budgetLines()->first();
    $currentPhotoPath = 'storage/u300/technical-sheets/reference-photos/current.jpg';
    $stalePhotoPath = 'storage/u300/technical-sheets/reference-photos/stale.jpg';

    $line->technicalSheet()->create([
        'item_name' => 'Material vigente',
        'goods_profile' => [[
            'description' => 'Microscopio vigente',
            'reference_photo_path' => $currentPhotoPath,
        ]],
    ]);

    expect(fn () => app(UpdateU300TechnicalSheets::class)->handle($program, [[
        'u300_budget_line_id' => $line->id,
        'goods' => [[
            'description' => 'Microscopio rezagado',
            'reference_photo_path' => $stalePhotoPath,
        ]],
    ]]))->toThrow(ValidationException::class);

    expect($line->technicalSheet()->first()->goods_profile[0])
        ->description->toBe('Microscopio vigente')
        ->reference_photo_path->toBe($currentPhotoPath);
});
