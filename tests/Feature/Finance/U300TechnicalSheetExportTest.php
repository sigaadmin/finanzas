<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

function u300TechnicalSheetExportUser(): User
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

function u300ProgramWithTechnicalSheet(User $user): U300Program
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
    $line = $version->budgetLines()->create([
        'u300_action_id' => $action->id,
        'expense_classification_id' => $classification->id,
        'amount_cents' => 16000000,
        'exercise_month' => 'OCT',
        'description' => 'Acción concentradora de la meta 5.1.',
        'justification' => 'Alimentos para movilidad académica.',
    ]);
    $line->technicalSheet()->create([
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
    ]);

    return $program;
}

test('finance operator can export technical sheets as a Word document', function () {
    $user = u300TechnicalSheetExportUser();
    $program = u300ProgramWithTechnicalSheet($user);

    $response = $this->actingAs($user)
        ->get(route('finance.u300.programs.technical-sheets.export', $program));

    $response->assertOk();
    $response->assertDownload('fichas-tecnicas-u300-2026.docx');

    $path = tempnam(sys_get_temp_dir(), 'u300-docx');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    expect($documentXml)
        ->toContain('ACCIÓN')
        ->toContain('5.1.2 Acción concentradora')
        ->toContain('37501 Viáticos en el país')
        ->toContain('Servicio de alimentos para movilidad académica')
        ->toContain('$160,000.00')
        ->toContain('CIENTO SESENTA MIL PESOS 00/100 M.N.')
        ->toContain('Propiciar movilidad académica.')
        ->toContain('Dra. Geraldine Díaz Argáez');
});
