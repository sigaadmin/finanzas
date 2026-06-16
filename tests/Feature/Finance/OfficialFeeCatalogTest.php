<?php

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\OfficialFeeLinkStatus;
use App\Enums\Finance\OfficialFeeScheduleStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\ChargeConcept;
use App\Models\ChargeConceptOfficialLink;
use App\Models\OfficialFeeConcept;
use App\Models\OfficialFeeSchedule;
use App\Models\PaymentProcedure;
use App\Models\PaymentProcedureItem;
use App\Models\StudentSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function officialCatalogUser(UserRole $role): User
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

test('finance manager can capture an official annual fee concept', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.official-fee-concepts.store'), [
            'fiscal_year' => 2026,
            'published_on' => '2026-01-15',
            'source_name' => 'Periódico Oficial del Estado de Quintana Roo',
            'code' => '16.1.1',
            'name' => 'Constancias de estudios de Educación Normal',
            'amount_pesos' => 10900,
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $schedule = OfficialFeeSchedule::firstWhere('fiscal_year', 2026);
    $officialConcept = OfficialFeeConcept::firstWhere('code', '16.1.1');

    expect($schedule)->not->toBeNull()
        ->and($schedule->status)->toBe(OfficialFeeScheduleStatus::Active)
        ->and($schedule->source_name)->toBe('Periódico Oficial del Estado de Quintana Roo')
        ->and($officialConcept)->not->toBeNull()
        ->and($officialConcept->schedule->is($schedule))->toBeTrue()
        ->and($officialConcept->name)->toBe('Constancias de estudios de Educación Normal')
        ->and($officialConcept->amount_pesos)->toBe(10900);
});

test('charge concept can be linked to an official concept for a fiscal year', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);
    $chargeConcept = ChargeConcept::factory()->create([
        'name' => 'Constancia de estudios',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
    ]);
    $officialConcept = OfficialFeeConcept::factory()->create([
        'code' => '16.1.1',
        'name' => 'Constancias de estudios de Educación Normal',
    ]);

    $this->actingAs($manager)
        ->put(route('finance.charge-concepts.official-link.update', $chargeConcept), [
            'fiscal_year' => 2026,
            'status' => 'linked',
            'official_fee_concept_id' => $officialConcept->id,
            'notes' => 'Vigente para el ejercicio.',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $link = ChargeConceptOfficialLink::firstWhere('charge_concept_id', $chargeConcept->id);

    expect($link)->not->toBeNull()
        ->and($link->status)->toBe(OfficialFeeLinkStatus::Linked)
        ->and($link->officialFeeConcept->is($officialConcept))->toBeTrue()
        ->and($link->fiscal_year)->toBe(2026);
});

test('charge concept can be explicitly marked as not applicable to DOF', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);
    $chargeConcept = ChargeConcept::factory()->create([
        'name' => 'Constancia de validación de título',
        'type' => ChargeConceptType::Internal,
    ]);

    $this->actingAs($manager)
        ->put(route('finance.charge-concepts.official-link.update', $chargeConcept), [
            'fiscal_year' => 2026,
            'status' => 'not_applicable',
            'official_fee_concept_id' => null,
            'notes' => 'Concepto interno sin publicación DOF.',
        ])
        ->assertRedirect(route('finance.charge-concepts.index'));

    $link = ChargeConceptOfficialLink::firstWhere('charge_concept_id', $chargeConcept->id);

    expect($link)->not->toBeNull()
        ->and($link->status)->toBe(OfficialFeeLinkStatus::NotApplicable)
        ->and($link->official_fee_concept_id)->toBeNull()
        ->and($link->notes)->toBe('Concepto interno sin publicación DOF.');
});

test('procedure items snapshot the official link data used at creation time', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);
    $student = StudentSnapshot::factory()->create();
    $officialConcept = OfficialFeeConcept::factory()->create([
        'code' => '16.1.1',
        'name' => 'Constancias de estudios de Educación Normal',
        'amount_pesos' => 10900,
    ]);
    $chargeConcept = ChargeConcept::factory()->create([
        'name' => 'Constancia de estudios',
        'amount_pesos' => 10900,
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
    ]);
    ChargeConceptOfficialLink::factory()->create([
        'charge_concept_id' => $chargeConcept->id,
        'official_fee_concept_id' => $officialConcept->id,
        'fiscal_year' => now()->year,
        'status' => OfficialFeeLinkStatus::Linked,
    ]);

    $procedure = PaymentProcedure::query()->create([
        'student_snapshot_id' => $student->id,
        'created_by' => $manager->id,
        'status' => 'draft',
        'total_pesos' => 10900,
    ]);

    $item = PaymentProcedureItem::query()->create([
        'payment_procedure_id' => $procedure->id,
        'charge_concept_id' => $chargeConcept->id,
        'concept_name' => $chargeConcept->name,
        'concept_type' => $chargeConcept->type,
        'unit_amount_pesos' => $chargeConcept->amount_pesos,
        'quantity' => 1,
        'subtotal_pesos' => $chargeConcept->amount_pesos,
    ]);

    expect($item->official_fee_link_status)->toBe(OfficialFeeLinkStatus::Linked)
        ->and($item->official_fee_fiscal_year)->toBe(now()->year)
        ->and($item->official_fee_code)->toBe('16.1.1')
        ->and($item->official_fee_name)->toBe('Constancias de estudios de Educación Normal')
        ->and($item->official_fee_amount_pesos)->toBe(10900);
});

test('concept catalog exposes official link state and options', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);
    $chargeConcept = ChargeConcept::factory()->create([
        'name' => 'Constancia de validación de título',
    ]);
    ChargeConceptOfficialLink::factory()->create([
        'charge_concept_id' => $chargeConcept->id,
        'fiscal_year' => now()->year,
        'status' => OfficialFeeLinkStatus::NotApplicable,
        'official_fee_concept_id' => null,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.charge-concepts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/concepts/index')
            ->where('official.fiscal_year', now()->year)
            ->has('official.concepts')
            ->where('concepts.data.0.official_link.status', 'not_applicable')
            ->where('concepts.data.0.official_link.label', 'No aplica DOF'));
});

test('concept catalog exposes readonly official concepts for selected fiscal year', function () {
    $manager = officialCatalogUser(UserRole::FinanceManager);
    OfficialFeeConcept::factory()->create([
        'code' => '16.1.1',
        'name' => 'Constancias de estudios de Educación Normal',
        'official_fee_schedule_id' => OfficialFeeSchedule::factory()->create([
            'fiscal_year' => 2026,
            'source_name' => 'Periódico Oficial del Estado de Quintana Roo',
        ])->id,
    ]);
    OfficialFeeConcept::factory()->create([
        'code' => '99.9.9',
        'name' => 'Concepto de otro ejercicio',
        'official_fee_schedule_id' => OfficialFeeSchedule::factory()->create([
            'fiscal_year' => 2025,
        ])->id,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.charge-concepts.index', ['fiscal_year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('official.fiscal_year', 2026)
            ->has('official.concepts', 1)
            ->where('official.concepts.0.code', '16.1.1')
            ->where('official.concepts.0.source_name', 'Periódico Oficial del Estado de Quintana Roo'));
});
