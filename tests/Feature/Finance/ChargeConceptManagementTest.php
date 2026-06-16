<?php

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\ChargeConcept;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function financeUser(UserRole $role): User
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

test('finance manager can view a filtered charge concept catalog', function () {
    $manager = financeUser(UserRole::FinanceManager);

    ChargeConcept::factory()->create([
        'name' => 'Constancias de estudios de Educacion Normal',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
    ]);

    ChargeConcept::factory()->create([
        'name' => 'Expedicion de credenciales de Educacion Normal',
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Inactive,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.charge-concepts.index', ['type' => 'external']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/concepts/index')
            ->where('filters.type', 'external')
            ->where('can.create', true)
            ->has('concepts.data', 1)
            ->where('concepts.data.0.name', 'Constancias de estudios de Educacion Normal')
            ->where('concepts.data.0.type', 'external')
            ->where('concepts.data.0.allows_quantity', false)
            ->where('concepts.data.0.can.update', true));
});

test('charge concept catalog exposes variable quantity setting to the interface', function () {
    $manager = financeUser(UserRole::FinanceManager);

    ChargeConcept::factory()->create([
        'name' => 'Constancia',
        'type' => ChargeConceptType::Internal,
        'allows_quantity' => true,
        'status' => ChargeConceptStatus::Active,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.charge-concepts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/concepts/index')
            ->where('concepts.data.0.name', 'Constancia')
            ->where('concepts.data.0.allows_quantity', true));
});

test('finance manager can create a charge concept with required financial type', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Examenes profesionales de Educacion Normal',
            'description' => 'Pago por tramite de examen profesional.',
            'amount_pesos' => 120000,
            'type' => 'external',
            'status' => 'active',
            'internal_key' => 'SEQ-EXPRO',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $concept = ChargeConcept::firstWhere('name', 'Examenes profesionales de Educacion Normal');

    expect($concept)->not->toBeNull()
        ->and($concept->type)->toBe(ChargeConceptType::External)
        ->and($concept->status)->toBe(ChargeConceptStatus::Active)
        ->and($concept->amount_pesos)->toBe(120000);
});

test('charge concepts are captured and exposed in whole pesos', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Constancia de estudios',
            'description' => 'Pago de constancia',
            'amount_pesos' => 1200,
            'type' => 'internal',
            'allows_quantity' => true,
            'status' => 'active',
            'internal_key' => 'INT-CONST',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $concept = ChargeConcept::firstWhere('name', 'Constancia de estudios');

    expect($concept)->not->toBeNull()
        ->and($concept->amount_pesos)->toBe(1200);

    $this->actingAs($manager)
        ->get(route('finance.charge-concepts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/concepts/index')
            ->where('concepts.data.0.amount_pesos', 1200));
});

test('finance manager can allow variable quantity only for internal concepts', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Constancias internas',
            'description' => 'Pago por constancias solicitadas.',
            'amount_pesos' => 8500,
            'type' => 'internal',
            'status' => 'active',
            'allows_quantity' => true,
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $internalConcept = ChargeConcept::firstWhere('name', 'Constancias internas');

    expect($internalConcept)->not->toBeNull()
        ->and($internalConcept->allows_quantity)->toBeTrue();

    $this->actingAs($manager)
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Inscripcion SEQ',
            'amount_pesos' => 200000,
            'type' => 'external',
            'status' => 'active',
            'allows_quantity' => true,
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $externalConcept = ChargeConcept::firstWhere('name', 'Inscripcion SEQ');

    expect($externalConcept)->not->toBeNull()
        ->and($externalConcept->allows_quantity)->toBeFalse();
});

test('charge concept type is required before a concept can be stored', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->from(route('finance.charge-concepts.index'))
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Concepto sin clasificar',
            'amount_pesos' => 10000,
            'status' => 'active',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'))
        ->assertSessionHasErrors('type');

    expect(ChargeConcept::where('name', 'Concepto sin clasificar')->exists())->toBeFalse();
});

test('finance manager can update a charge concept from the catalog', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $concept = ChargeConcept::factory()->create([
        'name' => 'Concepto inicial',
        'amount_pesos' => 10000,
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Active,
    ]);

    $this->actingAs($manager)
        ->put(route('finance.charge-concepts.update', $concept), [
            'name' => 'Concepto actualizado',
            'description' => 'Clasificado para reporte mensual.',
            'amount_pesos' => 25000,
            'type' => 'external',
            'status' => 'inactive',
            'internal_key' => 'SEQ-ACT',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $concept->refresh();

    expect($concept->name)->toBe('Concepto actualizado')
        ->and($concept->amount_pesos)->toBe(25000)
        ->and($concept->type)->toBe(ChargeConceptType::External)
        ->and($concept->status)->toBe(ChargeConceptStatus::Inactive)
        ->and($concept->internal_key)->toBe('SEQ-ACT');
});

test('finance manager can update an internal concept to allow variable quantity', function () {
    $manager = financeUser(UserRole::FinanceManager);

    $concept = ChargeConcept::factory()->create([
        'name' => 'Constancia',
        'amount_pesos' => 8500,
        'type' => ChargeConceptType::Internal,
        'allows_quantity' => false,
        'status' => ChargeConceptStatus::Active,
    ]);

    $this->actingAs($manager)
        ->put(route('finance.charge-concepts.update', $concept), [
            'name' => 'Constancia',
            'description' => null,
            'amount_pesos' => 8500,
            'type' => 'internal',
            'allows_quantity' => true,
            'status' => 'active',
            'internal_key' => null,
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $concept->refresh();

    expect($concept->allows_quantity)->toBeTrue();
});

test('finance assistant cannot configure charge concepts', function () {
    $assistant = financeUser(UserRole::FinanceAssistant);

    $this->actingAs($assistant)
        ->get(route('finance.charge-concepts.index'))
        ->assertForbidden();

    $this->actingAs($assistant)
        ->post(route('finance.charge-concepts.store'), [
            'name' => 'Examen de ingreso a licenciatura de Educacion Normal',
            'amount_pesos' => 90000,
            'type' => 'external',
            'status' => 'active',
        ])
        ->assertForbidden();
});
