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

function u300ProgramDashboardUser(): User
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

function u300ProgramForDashboard(User $user): U300Program
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
        'requested_total_cents' => 20000000,
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
        'requested_total_cents' => 20000000,
        'approved_total_cents' => 16000000,
    ]);
    $firstAction = $goal->actions()->create([
        'number' => '5.1.1',
        'name' => 'Acción con ficha',
        'justification' => 'Justificación uno.',
        'requested_total_cents' => 10000000,
        'approved_total_cents' => 8000000,
    ]);
    $secondAction = $goal->actions()->create([
        'number' => '5.1.2',
        'name' => 'Acción pendiente',
        'justification' => 'Justificación dos.',
        'requested_total_cents' => 10000000,
        'approved_total_cents' => 8000000,
    ]);
    $firstLine = $version->budgetLines()->create([
        'u300_action_id' => $firstAction->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 10000000,
        'exercise_month' => 'OCT',
        'description' => 'Partida con COG y ficha.',
        'justification' => 'Viáticos.',
        'sort_order' => 1,
    ]);
    $version->budgetLines()->create([
        'u300_action_id' => $firstAction->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 1250000,
        'exercise_month' => 'NOV',
        'description' => 'Segunda partida COG para la misma acción.',
        'justification' => 'Viáticos complementarios.',
        'sort_order' => 2,
    ]);
    $secondLine = $version->budgetLines()->create([
        'u300_action_id' => $secondAction->id,
        'amount_cents' => 6000000,
        'exercise_month' => 'NOV',
        'description' => 'Partida pendiente de COG y ficha.',
        'sort_order' => 3,
    ]);
    $firstLine->technicalSheet()->create([
        'objective' => 'Propiciar movilidad académica.',
        'work_description' => 'Comprar alimentos.',
        'technical_specs' => 'Alimentos para estudiantes.',
        'beneficiaries' => '3 estudiantes',
        'scheduled_date' => 'Octubre de 2026',
        'deliverables' => 'Informe.',
        'delivery_location' => 'CREN.',
        'supervisor' => 'Dra. Geraldine Díaz Argáez',
        'payment_terms' => 'Transferencia.',
    ]);
    $firstLine->movements()->create([
        'recorded_by' => $user->id,
        'type' => 'expense',
        'movement_date' => '2026-10-19',
        'concept' => 'Anticipo de viáticos.',
        'document_reference' => 'SOL-001',
        'amount_cents' => 2500000,
    ]);
    $secondLine->movements()->create([
        'recorded_by' => $user->id,
        'type' => 'expense',
        'movement_date' => '2026-11-01',
        'concept' => 'Movimiento cancelado.',
        'document_reference' => 'SOL-002',
        'amount_cents' => 1000000,
        'cancelled_at' => now(),
        'cancelled_by' => $user->id,
        'cancellation_reason' => 'Captura de prueba.',
    ]);

    return $program;
}

test('finance operator can see the U300 program dashboard summary', function () {
    $user = u300ProgramDashboardUser();
    $program = u300ProgramForDashboard($user);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/show')
            ->where('program.id', $program->id)
            ->where('program.summary.approved_total_cents', 16000000)
            ->where('program.summary.adjusted_total_cents', 17250000)
            ->where('program.summary.executed_cents', 2500000)
            ->where('program.summary.available_cents', 14750000)
            ->where('program.summary.lines_count', 3)
            ->where('program.summary.lines_without_cog_count', 1)
            ->where('program.summary.lines_without_technical_sheet_count', 2)
            ->where('program.summary.active_movements_count', 1)
            ->where('program.summary.cancelled_movements_count', 1)
            ->where('program.lines.0.status', 'Completa')
            ->where('program.lines.1.status', 'Pendiente ficha')
            ->where('program.lines.2.status', 'Pendiente COG / ficha')
            ->where('program.actions.0.action_number', '5.1.1')
            ->where('program.actions.0.amount_cents', 11250000)
            ->has('program.actions.0.cog_lines', 2)
            ->where('program.actions.0.cog_lines.0.cog_code', '37501')
            ->where('program.actions.0.cog_lines.0.cog_name', 'Viáticos en el país')
            ->where('program.actions.0.cog_lines.0.exercise_month', 'OCT')
            ->where('program.actions.0.cog_lines.0.amount_cents', 10000000)
            ->where(
                'program.actions.0.cog_lines.0.technical_sheet_url',
                route('finance.u300.programs.technical-sheets.edit', $program).'#ficha-tecnica-'.$program->budgetVersions()->first()->budgetLines()->orderBy('sort_order')->first()->id,
            )
            ->where('program.actions.0.cog_lines.1.exercise_month', 'NOV')
            ->where('program.actions.0.cog_lines.1.amount_cents', 1250000)
            ->where('program.actions.1.action_number', '5.1.2')
            ->where('program.actions.1.amount_cents', 6000000)
            ->has('program.actions.1.cog_lines', 1)
            ->where('program.actions.1.cog_lines.0.cog_code', null)
            ->where('program.actions.1.cog_lines.0.cog_name', null)
            ->where('program.actions.1.cog_lines.0.exercise_month', 'NOV')
            ->where('program.actions.1.cog_lines.0.amount_cents', 6000000));
});

test('finance operator can export the U300 program dashboard as CSV', function () {
    $user = u300ProgramDashboardUser();
    $program = u300ProgramForDashboard($user);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.summary.export', $program));

    $response->assertOk();
    $response->assertDownload('resumen-u300-2026.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Acción,COG,Mes,"Monto adecuado",Ejercido,Disponible,Estado')
        ->toContain('"5.1.1 Acción con ficha","37501 Viáticos en el país",OCT,100000.00,25000.00,75000.00,Completa')
        ->toContain('"5.1.2 Acción pendiente","Sin COG",NOV,60000.00,0.00,60000.00,"Pendiente COG / ficha"');
});

test('finance operator can export the U300 program dashboard as an Excel workbook', function () {
    $user = u300ProgramDashboardUser();
    $program = u300ProgramForDashboard($user);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.summary.export-xlsx', $program));

    $response->assertOk();
    $response->assertDownload('resumen-u300-2026.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'u300-xlsx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    expect($sheetXml)
        ->toContain('Información financiera U300')
        ->toContain('5.1.1 Acción con ficha')
        ->toContain('37501 Viáticos en el país')
        ->toContain('100000.00')
        ->toContain('25000.00')
        ->toContain('75000.00')
        ->toContain('Pendiente COG / ficha');
});

test('finance operator can see U300 financial reports after COG conversion', function () {
    $user = u300ProgramDashboardUser();
    $program = u300ProgramForDashboard($user);
    $equipmentClassification = ExpenseClassification::create([
        'fiscal_year' => 2026,
        'chapter_code' => '5000',
        'chapter_name' => 'Bienes muebles, inmuebles e intangibles',
        'concept_code' => '5100',
        'concept_name' => 'Mobiliario y equipo de administración',
        'generic_item_code' => '5150',
        'generic_item_name' => 'Equipo de cómputo',
        'specific_item_code' => '51501',
        'specific_item_name' => 'Equipo de cómputo y tecnología de la información',
        'expense_type_code' => '2',
        'expense_type_name' => 'Gasto de inversión',
    ]);
    $program->budgetVersions()->first()->budgetLines()->create([
        'u300_action_id' => $program->projects()->first()->goals()->first()->actions()->where('number', '5.1.2')->first()->id,
        'expense_classification_id' => $equipmentClassification->id,
        'amount_cents' => 3000000,
        'exercise_month' => 'DIC',
        'description' => 'Partida COG adicional para dashboard.',
        'sort_order' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.financial-reports.show', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/financial-reports')
            ->where('program.id', $program->id)
            ->where('reports.desglose.0.project', '5. Proyecto de evaluación institucional.')
            ->where('reports.desglose.0.goal', '5.1 Meta con redistribución permitida.')
            ->where('reports.desglose.0.action', '5.1.1 Acción con ficha')
            ->where('reports.desglose.0.cog_code', '37501')
            ->where('reports.desglose.0.amount_cents', 10000000)
            ->where('reports.desglose.0.month', 'OCT')
            ->where('reports.concentrado.0.cog_code', '37501')
            ->where('reports.concentrado.0.amount_cents', 11250000)
            ->where('reports.concentrado.0.executed_cents', 2500000)
            ->where('reports.concentrado.0.available_cents', 8750000)
            ->where('reports.presupuesto.0.months.OCT', 10000000)
            ->where('reports.presupuesto.0.months.NOV', 1250000)
            ->where('reports.presupuesto_totals.months.OCT', 10000000)
            ->where('reports.presupuesto_totals.total_cents', 14250000)
            ->where('reports.dashboard.by_action.0.label', '5.1.1 Acción con ficha')
            ->where('reports.dashboard.by_action.0.amount_cents', 11250000)
            ->where('reports.dashboard.by_action.1.label', '5.1.2 Acción pendiente')
            ->where('reports.dashboard.by_action.1.amount_cents', 3000000)
            ->where('reports.dashboard.by_partida.0.label', '37501 Viáticos en el país')
            ->where('reports.dashboard.by_partida.0.amount_cents', 11250000)
            ->where('reports.dashboard.by_chapter.0.label', '3000 Servicios Generales')
            ->where('reports.dashboard.by_chapter.0.amount_cents', 11250000)
            ->where('reports.dashboard.by_chapter.1.label', '5000 Bienes muebles, inmuebles e intangibles')
            ->where('reports.dashboard.by_chapter.1.amount_cents', 3000000)
            ->where('reports.dashboard.partida_by_month.0.months.OCT', 10000000)
            ->where('reports.dashboard.partida_by_month.0.months.NOV', 1250000)
            ->where('reports.dashboard.partida_by_month.1.months.DIC', 3000000));
});

test('finance operator can export U300 financial reports workbook', function () {
    $user = u300ProgramDashboardUser();
    $program = u300ProgramForDashboard($user);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.financial-reports.export', $program));

    $response->assertOk();
    $response->assertDownload('reportes-financieros-u300-2026.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'u300-reports-xlsx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $sheet1Xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sheet2Xml = $zip->getFromName('xl/worksheets/sheet2.xml');
    $sheet3Xml = $zip->getFromName('xl/worksheets/sheet3.xml');
    $zip->close();
    unlink($path);

    expect($workbookXml)
        ->toContain('DESGLOSE')
        ->toContain('CONCENTRADO')
        ->toContain('PRESUPUESTO');
    expect($sheet1Xml)->toContain('Proyecto de evaluación institucional.');
    expect($sheet2Xml)->toContain('Viáticos en el país');
    expect($sheet3Xml)->toContain('OCT');
});
