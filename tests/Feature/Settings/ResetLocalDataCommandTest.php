<?php

use App\Models\Finance\U300\U300Program;

beforeEach(function () {
    app()->detectEnvironment(fn (): string => 'local');
});

afterEach(function () {
    app()->detectEnvironment(fn (): string => 'testing');
});

test('command rejects unknown reset scopes', function () {
    $this->artisan('finance:reset-local-data', ['scope' => 'unknown', '--force' => true])
        ->expectsOutputToContain('El alcance debe ser ventanilla, u300, own-revenue o all.')
        ->assertFailed();
});

test('command cancellation leaves local data unchanged', function () {
    createCommandU300Program();

    $this->artisan('finance:reset-local-data', ['scope' => 'u300'])
        ->expectsConfirmation('Esta operación eliminará permanentemente los datos locales de U300. ¿Desea continuar?', 'no')
        ->expectsOutputToContain('Operación cancelada.')
        ->assertFailed();

    expect(U300Program::query()->count())->toBe(1);
});

test('command force option resets the selected scope', function () {
    createCommandU300Program();

    $this->artisan('finance:reset-local-data', ['scope' => 'u300', '--force' => true])
        ->expectsOutputToContain('U300 se reinició correctamente')
        ->assertSuccessful();

    expect(U300Program::query()->count())->toBe(0);
});

test('command cannot bypass the local environment restriction', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('finance:reset-local-data', ['scope' => 'u300', '--force' => true])
        ->expectsOutputToContain('El reinicio sólo está disponible en local.')
        ->assertFailed();
});

function createCommandU300Program(): U300Program
{
    return U300Program::factory()->create([
        'fiscal_year' => 2026,
        'name' => 'Programa U300 del comando',
        'objective' => 'Probar el comando local.',
        'justification' => 'Registro de prueba.',
        'responsible_name' => 'Responsable de prueba',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Lic.',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
}
