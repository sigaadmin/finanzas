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
        'exercise_month' => 'OCT',
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
