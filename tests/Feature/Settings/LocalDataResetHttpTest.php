<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutVite();
    app()->detectEnvironment(fn (): string => 'local');
});

afterEach(function () {
    app()->detectEnvironment(fn (): string => 'testing');
});

test('local data reset responds as not found outside local', function () {
    $owner = createLocalDataResetOwner();
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs($owner)
        ->get(route('local-data.index'))
        ->assertNotFound();
});

test('only the owner can open local data reset', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('local-data.index'))
        ->assertForbidden();
});

test('owner sees the four reset scopes in operational language', function () {
    $this->actingAs(createLocalDataResetOwner())
        ->get(route('local-data.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/local-data')
            ->where('localDataResetAvailable', true)
            ->has('scopes', 4)
            ->where('scopes.0.value', 'ventanilla')
            ->where('scopes.0.confirmation_phrase', 'BORRAR VENTANILLA')
            ->where('scopes.0.preserves.0', 'Conceptos de cobro y tarifas oficiales')
            ->where('scopes.3.value', 'all')
            ->where('scopes.3.is_global', true));
});

test('local reset navigation availability is hidden from non owners', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/appearance')
            ->where('localDataResetAvailable', false));
});

test('the exact phrase is required before resetting a scope', function () {
    createHttpU300Program();

    $this->actingAs(createLocalDataResetOwner())
        ->post(route('local-data.reset', 'u300'), ['confirmation' => 'BORRAR'])
        ->assertSessionHasErrors('confirmation');

    expect(U300Program::query()->count())->toBe(1);
});

test('owner can reset one scope and receives a result message', function () {
    createHttpU300Program();

    $this->actingAs(createLocalDataResetOwner())
        ->post(route('local-data.reset', 'u300'), ['confirmation' => 'BORRAR U300'])
        ->assertRedirect(route('local-data.index'))
        ->assertInertiaFlash('success', 'U300 se reinició correctamente: 1 registro eliminado.');

    expect(U300Program::query()->count())->toBe(0);
});

test('general reset closes the current session and redirects home', function () {
    $owner = createLocalDataResetOwner();
    User::factory()->create(['email' => 'secondary@crenfcp.edu.mx']);

    $this->actingAs($owner)
        ->post(route('local-data.reset', 'all'), ['confirmation' => 'REINICIAR TODO'])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::query()->pluck('email')->all())->toBe(['administrador.siga@crenfcp.edu.mx']);
});

function createLocalDataResetOwner(): User
{
    $owner = User::factory()->create([
        'email' => 'administrador.siga@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $owner->email,
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    return $owner;
}

function createHttpU300Program(): U300Program
{
    return U300Program::factory()->create([
        'fiscal_year' => 2026,
        'name' => 'Programa U300 de HTTP',
        'objective' => 'Probar el endpoint local.',
        'justification' => 'Registro de prueba.',
        'responsible_name' => 'Responsable de prueba',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Lic.',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
}
