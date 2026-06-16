<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('local auto login signs in configured authorized user', function () {
    config([
        'finance.local_auto_login.enabled' => true,
        'finance.local_auto_login.email' => 'administrador.siga@crenfcp.edu.mx',
        'finance.local_auto_login.environments' => ['testing'],
    ]);

    AuthorizedAccess::create([
        'email' => 'administrador.siga@crenfcp.edu.mx',
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $this->get(route('dashboard'))->assertOk();

    $this->assertAuthenticated();

    expect(auth()->user()->email)->toBe('administrador.siga@crenfcp.edu.mx')
        ->and(AuthorizedAccess::where('email', 'administrador.siga@crenfcp.edu.mx')->first()->last_used_at)->not->toBeNull();
});

test('local auto login does not sign in users without active authorized access', function () {
    config([
        'finance.local_auto_login.enabled' => true,
        'finance.local_auto_login.email' => 'sin-acceso@crenfcp.edu.mx',
        'finance.local_auto_login.environments' => ['testing'],
    ]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('local auto login is ignored outside local environment', function () {
    config([
        'finance.local_auto_login.enabled' => true,
        'finance.local_auto_login.email' => 'administrador.siga@crenfcp.edu.mx',
        'finance.local_auto_login.environments' => ['local'],
    ]);

    AuthorizedAccess::create([
        'email' => 'administrador.siga@crenfcp.edu.mx',
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    User::factory()->create([
        'email' => 'administrador.siga@crenfcp.edu.mx',
    ]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});
