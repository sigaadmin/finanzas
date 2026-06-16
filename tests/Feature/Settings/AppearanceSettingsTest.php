<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings redirects to appearance only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/appearance');
});

test('appearance settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/appearance'),
        );
});

test('profile and security settings are not exposed to users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/settings/security')
        ->assertNotFound();
});
