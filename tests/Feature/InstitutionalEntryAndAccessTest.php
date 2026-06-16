<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function institutionalAccessUser(): User
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

beforeEach(function () {
    $this->withoutVite();
});

test('welcome page is institutional and does not expose public registration', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('welcome')
            ->where('app.name', 'Portal Financiero CREN')
            ->where('access.registration_enabled', false)
            ->where('access.domain', 'crenfcp.edu.mx')
        );
});

test('public registration route is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('authenticated users without authorized access cannot enter dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('authorized finance users can enter dashboard', function () {
    $this->actingAs(institutionalAccessUser())
        ->get(route('dashboard'))
        ->assertOk();
});
