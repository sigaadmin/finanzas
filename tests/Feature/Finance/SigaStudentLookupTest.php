<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'finance.siga.base_url' => 'https://siga.test',
        'finance.siga.token' => 'secret-token',
        'finance.siga.timeout' => 5,
    ]);

    Http::preventStrayRequests();
});

function financeOperator(UserRole $role = UserRole::FinanceAssistant): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

test('finance operator can search students from siga2', function () {
    Http::fake([
        'https://siga.test/api/internal/finance/v1/students/search*' => Http::response([
            'data' => [
                [
                    'id' => 100,
                    'matricula' => '20260001',
                    'nombre_completo' => 'Maria Lopez Chan',
                    'programa' => [
                        'id' => 1,
                        'nombre' => 'Licenciatura en Educacion Normal',
                        'nombre_corto' => 'LEN',
                    ],
                    'semestre' => 2,
                    'grupo' => 'B',
                    'estatus' => 'active',
                ],
            ],
        ]),
    ]);

    $this->actingAs(financeOperator())
        ->getJson(route('finance.students.search', ['q' => 'Maria']))
        ->assertOk()
        ->assertJsonPath('data.0.siga_student_id', '100')
        ->assertJsonPath('data.0.name', 'Maria Lopez Chan')
        ->assertJsonPath('data.0.grade', '2')
        ->assertJsonPath('data.0.group', 'B');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->url() === 'https://siga.test/api/internal/finance/v1/students/search?query=Maria&include_graduates=1&limit=10');
});

test('finance operator can search graduated students from siga2', function () {
    Http::fake([
        'https://siga.test/api/internal/finance/v1/students/search*' => Http::response([
            'data' => [
                [
                    'id' => 'SIGA-900',
                    'matricula' => '20190001',
                    'name' => 'Ana Maria Pech',
                    'program' => 'Licenciatura en Educacion Normal',
                    'grade' => null,
                    'group' => null,
                    'academic_status' => 'egresada',
                ],
            ],
        ]),
    ]);

    $this->actingAs(financeOperator())
        ->getJson(route('finance.students.search', ['q' => 'Ana']))
        ->assertOk()
        ->assertJsonPath('data.0.siga_student_id', 'SIGA-900')
        ->assertJsonPath('data.0.name', 'Ana Maria Pech')
        ->assertJsonPath('data.0.academic_status', 'egresada');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'include_graduates=1'));
});

test('student search returns an empty list when siga2 has no matches', function () {
    Http::fake([
        'https://siga.test/api/internal/finance/v1/students/search*' => Http::response([
            'data' => [],
        ]),
    ]);

    $this->actingAs(financeOperator())
        ->getJson(route('finance.students.search', ['q' => 'ZZZ']))
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('student search fails clearly when siga2 is unavailable', function () {
    Http::fake([
        'https://siga.test/api/internal/finance/v1/students/search*' => Http::response([], 503),
    ]);

    $this->actingAs(financeOperator())
        ->getJson(route('finance.students.search', ['q' => 'Maria']))
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'No se pudo consultar SIGA2.');
});

test('users without finance operation access cannot search students', function () {
    $user = User::factory()->create([
        'email' => 'sin.acceso@crenfcp.edu.mx',
    ]);

    $this->actingAs($user)
        ->getJson(route('finance.students.search', ['q' => 'Maria']))
        ->assertForbidden();
});
