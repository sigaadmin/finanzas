<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;

function accessManager(UserRole $role): User
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

test('administrators can grant individual module permissions to institutional users', function () {
    $administrator = accessManager(UserRole::Admin);

    $this->actingAs($administrator)
        ->post(route('authorized-accesses.store'), [
            'email' => 'operador@crenfcp.edu.mx',
            'role' => UserRole::FinanceAssistant->value,
            'can_operate_ventanilla' => true,
            'can_operate_u300' => false,
            'can_operate_own_revenue' => true,
        ])
        ->assertRedirect(route('authorized-accesses.index'));

    $this->assertDatabaseHas('authorized_accesses', [
        'email' => 'operador@crenfcp.edu.mx',
        'role' => UserRole::FinanceAssistant->value,
        'can_operate_ventanilla' => true,
        'can_operate_u300' => false,
        'can_operate_own_revenue' => true,
    ]);
});

test('user management rejects accounts outside the institutional domain', function () {
    $owner = accessManager(UserRole::Owner);

    $this->actingAs($owner)
        ->post(route('authorized-accesses.store'), [
            'email' => 'external@example.com',
            'role' => UserRole::FinanceAssistant->value,
        ])
        ->assertSessionHasErrors('email');
});
