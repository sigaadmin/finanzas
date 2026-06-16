<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function u300ProgramIndexUser(): User
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

test('finance operator can access the U300 program index from the module route', function () {
    $user = u300ProgramIndexUser();

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
    $program->budgetVersions()->create([
        'created_by' => $user->id,
        'kind' => 'adjusted',
        'name' => 'Adecuación presupuestal',
        'status' => 'draft',
        'total_cents' => 16000000,
    ]);

    $this->actingAs($user)
        ->get(route('finance.u300.programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/u300/programs/index')
            ->where('programs.0.id', $program->id)
            ->where('programs.0.fiscal_year', 2026)
            ->where('programs.0.name', '0. Proyecto General U300')
            ->where('programs.0.requested_total_cents', 20000000)
            ->where('programs.0.approved_total_cents', 16000000)
            ->where('programs.0.adjusted_total_cents', 16000000));
});

test('sidebar links the U300 menu item to the program index', function () {
    $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));

    expect($sidebar)
        ->toContain("title: 'Presupuesto U300'")
        ->toContain('href: finance.u300.programs.index()')
        ->not->toContain('href: finance.u300.imports.create()');
});
