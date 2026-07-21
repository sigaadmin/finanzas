<?php

use App\Actions\Finance\ImportExpenseClassifications;
use App\Actions\Finance\U300\StoreU300ImportedProject;
use App\Actions\Finance\U300\UpdateU300BudgetAdjustment;
use App\Actions\Finance\U300\UpdateU300FederalVerdict;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function createMinimalCogXlsxForFeature(string $path): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
      <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
      <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    </Types>
    XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>
    XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <sheets><sheet name="COG" sheetId="1" r:id="rId1"/></sheets>
    </workbook>
    XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    </Relationships>
    XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
      <sheetData>
        <row r="1">
          <c r="A1" t="inlineStr"><is><t>Cve Capítulo</t></is></c><c r="B1" t="inlineStr"><is><t>Capítulo</t></is></c><c r="C1" t="inlineStr"><is><t>Cve Concepto</t></is></c><c r="D1" t="inlineStr"><is><t>Concepto</t></is></c><c r="E1" t="inlineStr"><is><t>Cve Partida Genérica</t></is></c><c r="F1" t="inlineStr"><is><t>Partida Genérica</t></is></c><c r="G1" t="inlineStr"><is><t>Cve Partida Específica</t></is></c><c r="H1" t="inlineStr"><is><t>Partida Específica</t></is></c><c r="I1" t="inlineStr"><is><t>Cve Tipo de Gasto</t></is></c><c r="J1" t="inlineStr"><is><t>Tipo de Gasto</t></is></c>
        </row>
        <row r="2">
          <c r="A2" t="inlineStr"><is><t>3000</t></is></c><c r="B2" t="inlineStr"><is><t>Servicios Generales</t></is></c><c r="C2" t="inlineStr"><is><t>3700</t></is></c><c r="D2" t="inlineStr"><is><t>Servicios de traslado y viáticos</t></is></c><c r="E2" t="inlineStr"><is><t>3750</t></is></c><c r="F2" t="inlineStr"><is><t>Viáticos en el país</t></is></c><c r="G2" t="inlineStr"><is><t>37501</t></is></c><c r="H2" t="inlineStr"><is><t>Viáticos en el país</t></is></c><c r="I2" t="inlineStr"><is><t>1</t></is></c><c r="J2" t="inlineStr"><is><t>Gasto corriente</t></is></c>
        </row>
      </sheetData>
    </worksheet>
    XML);
    $zip->close();
}

function u300CogUser(): User
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

function u300ProgramWithAdjustedLine(User $user): U300Program
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
                'requested_total_cents' => 16000000,
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
                            'requested_total_cents' => 16000000,
                            'actions' => [
                                [
                                    'number' => '5.1.2',
                                    'name' => 'Acción concentradora',
                                    'justification' => 'Justificación dos.',
                                    'items' => [
                                        [
                                            'expense_concept' => 'Alimentación',
                                            'expense_item' => 'Atención a terceros',
                                            'period' => 4,
                                            'quantity' => 1,
                                            'unit_price_cents' => 16000000,
                                            'total_cents' => 16000000,
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
    $item = $program->projects->first()->goals->first()->actions->first()->requestedItems->first();
    $program = app(UpdateU300FederalVerdict::class)->handle($program, [
        [
            'id' => $item->id,
            'approved_amount_cents' => 16000000,
            'approved_percentage' => 100,
        ],
    ]);
    $action = $program->projects->first()->goals->first()->actions->first();

    return app(UpdateU300BudgetAdjustment::class)->handle($program, $user, [
        [
            'u300_action_id' => $action->id,
            'amount_cents' => 16000000,
            'description' => 'Acción concentradora de la meta 5.1.',
        ],
    ]);
}

test('finance operator can import the COG catalog and assign classifications with an action justification', function () {
    $user = u300CogUser();
    $program = u300ProgramWithAdjustedLine($user);
    $xlsxPath = tempnam(sys_get_temp_dir(), 'cog').'.xlsx';
    createMinimalCogXlsxForFeature($xlsxPath);

    $imported = app(ImportExpenseClassifications::class)->handle($user, 2026, $xlsxPath);

    expect($imported)->toBe(1);

    $classification = ExpenseClassification::query()
        ->where('specific_item_code', '37501')
        ->firstOrFail();
    $secondClassification = ExpenseClassification::create([
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
    $program->load('budgetVersions.budgetLines');
    $line = $program->budgetVersions->firstWhere('kind', 'adjusted')->budgetLines->first();
    $line->update([
        'exercise_month' => 'AGO',
        'justification' => 'Justificación anterior de partida.',
    ]);
    $line->technicalSheet()->create([
        'scheduled_date' => 'Agosto de 2026',
    ]);
    $action = $line->action()->firstOrFail();

    $this->actingAs($user)
        ->get(route('finance.u300.programs.cog.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/cog')
            ->where('program.id', $program->id)
            ->where('classifications.0.specific_item_code', '21101')
            ->where('classifications.1.specific_item_code', '37501')
            ->where('program.actions.0.id', $line->u300_action_id)
            ->where('program.actions.0.justification', 'Justificación dos.')
            ->where('program.actions.0.lines.0.id', $line->id));

    $this->actingAs($user)
        ->put(route('finance.u300.programs.cog.update', $program), [
            'actions' => [
                [
                    'id' => $action->id,
                    'justification' => 'Justificación de acción para movilidad académica.',
                ],
            ],
            'lines' => [
                [
                    'id' => $line->id,
                    'u300_action_id' => $line->u300_action_id,
                    'amount_cents' => 10000000,
                    'expense_classification_code' => $classification->specific_item_code,
                    'exercise_month' => 'OCT',
                ],
                [
                    'id' => null,
                    'u300_action_id' => $line->u300_action_id,
                    'amount_cents' => 6000000,
                    'expense_classification_code' => $secondClassification->specific_item_code,
                    'exercise_month' => 'NOV',
                ],
            ],
        ])
        ->assertRedirect(route('finance.u300.programs.show', $program));

    $this->assertDatabaseHas('u300_actions', [
        'id' => $action->id,
        'justification' => 'Justificación de acción para movilidad académica.',
    ]);
    $this->assertDatabaseHas('u300_budget_lines', [
        'id' => $line->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 10000000,
        'exercise_month' => 'OCT',
        'justification' => null,
    ]);
    $this->assertDatabaseHas('u300_technical_sheets', [
        'u300_budget_line_id' => $line->id,
        'scheduled_date' => 'Octubre de 2026',
    ]);
    $this->assertDatabaseHas('u300_budget_lines', [
        'u300_action_id' => $line->u300_action_id,
        'expense_classification_id' => $secondClassification->id,
        'amount_cents' => 6000000,
        'exercise_month' => 'NOV',
    ]);
});
