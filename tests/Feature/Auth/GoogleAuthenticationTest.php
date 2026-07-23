<?php

use App\Actions\Settings\EnsureInstitutionalOwner;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

test('guests are redirected to Google for institutional authentication', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))
        ->assertRedirect();
});

test('password credentials cannot authenticate users', function () {
    $user = User::factory()->create([
        'email' => 'legacy@crenfcp.edu.mx',
        'password' => 'password',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an authorized institutional account can authenticate with Google', function () {
    AuthorizedAccess::query()->create([
        'email' => 'finanzas@crenfcp.edu.mx',
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    Socialite::fake('google', GoogleUser::fake([
        'id' => 'google-finanzas',
        'name' => 'Finanzas CREN',
        'email' => 'FINANZAS@CRENFCP.EDU.MX',
        'hd' => 'crenfcp.edu.mx',
        'verified_email' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'finanzas@crenfcp.edu.mx',
        'name' => 'Finanzas CREN',
    ]);

    expect(AuthorizedAccess::query()->firstOrFail()->last_used_at)->not->toBeNull();
});

test('Google accounts outside the institutional domain cannot authenticate', function () {
    Socialite::fake('google', GoogleUser::fake([
        'id' => 'external-account',
        'name' => 'External Account',
        'email' => 'external@example.com',
        'hd' => 'example.com',
        'verified_email' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->assertDatabaseMissing('users', ['email' => 'external@example.com']);
});

test('institutional accounts without active authorization cannot authenticate', function () {
    AuthorizedAccess::query()->create([
        'email' => 'inactive@crenfcp.edu.mx',
        'role' => UserRole::FinanceManager,
        'is_active' => false,
    ]);

    Socialite::fake('google', GoogleUser::fake([
        'id' => 'inactive-account',
        'name' => 'Inactive Account',
        'email' => 'inactive@crenfcp.edu.mx',
        'hd' => 'crenfcp.edu.mx',
        'verified_email' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->assertDatabaseMissing('users', ['email' => 'inactive@crenfcp.edu.mx']);
});

test('Google provider failures do not authenticate users', function () {
    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andThrow(new RuntimeException('Google is unavailable'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('unverified or non-Workspace Google accounts cannot authenticate', function (array $attributes) {
    AuthorizedAccess::query()->create([
        'email' => 'finanzas@crenfcp.edu.mx',
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    Socialite::fake('google', GoogleUser::fake([
        'id' => 'untrusted-account',
        'name' => 'Finanzas CREN',
        'email' => 'finanzas@crenfcp.edu.mx',
        ...$attributes,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
})->with([
    'unverified email' => [['hd' => 'crenfcp.edu.mx', 'verified_email' => false]],
    'missing Workspace domain' => [['verified_email' => true]],
]);

test('the institutional owner is permanently authorized with the owner role', function () {
    app(EnsureInstitutionalOwner::class)->handle();

    $access = AuthorizedAccess::query()
        ->where('email', 'administrador.siga@crenfcp.edu.mx')
        ->firstOrFail();

    expect($access->role)->toBe(UserRole::Owner)
        ->and($access->is_active)->toBeTrue();
});
