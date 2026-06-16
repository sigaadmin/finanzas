<?php

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use App\Models\StudentSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function financeProcedureUser(UserRole $role = UserRole::FinanceAssistant): User
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

function studentPayload(): array
{
    return [
        'siga_student_id' => 'SIGA-200',
        'matricula' => '20260002',
        'name' => 'Jose Manuel Pool',
        'program' => 'Licenciatura en Educacion Normal',
        'grade' => '3',
        'group' => 'A',
        'academic_status' => 'active',
    ];
}

test('finance operator can view procedure list and create page', function () {
    $assistant = financeProcedureUser();

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/procedures/index')
            ->has('procedures.data'));

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/procedures/create')
            ->has('concepts'));
});

test('finance operator can filter procedure list by concept date student and status', function () {
    $assistant = financeProcedureUser();

    $constancyConcept = ChargeConcept::factory()->create([
        'name' => 'Constancia de estudios',
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Active,
    ]);

    $titleConcept = ChargeConcept::factory()->create([
        'name' => 'Titulacion',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
    ]);

    $matchingProcedure = PaymentProcedure::factory()
        ->hasItems(1, [
            'charge_concept_id' => $constancyConcept->id,
            'concept_name' => $constancyConcept->name,
            'concept_type' => $constancyConcept->type,
        ])
        ->create([
            'student_snapshot_id' => StudentSnapshot::factory()->create([
                'name' => 'Ana Lucia Canul',
            ]),
            'status' => PaymentProcedureStatus::PendingPayment,
            'created_at' => '2026-06-05 10:30:00',
        ]);

    PaymentProcedure::factory()
        ->hasItems(1, [
            'charge_concept_id' => $titleConcept->id,
            'concept_name' => $titleConcept->name,
            'concept_type' => $titleConcept->type,
        ])
        ->create([
            'student_snapshot_id' => StudentSnapshot::factory()->create([
                'name' => 'Ana Lucia Canul',
            ]),
            'status' => PaymentProcedureStatus::PendingPayment,
            'created_at' => '2026-06-05 11:00:00',
        ]);

    PaymentProcedure::factory()
        ->hasItems(1, [
            'charge_concept_id' => $constancyConcept->id,
            'concept_name' => $constancyConcept->name,
            'concept_type' => $constancyConcept->type,
        ])
        ->create([
            'student_snapshot_id' => StudentSnapshot::factory()->create([
                'name' => 'Carlos Martin',
            ]),
            'status' => PaymentProcedureStatus::Paid,
            'created_at' => '2026-06-04 10:30:00',
        ]);

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.index', [
            'procedure_type' => (string) $constancyConcept->id,
            'date' => '2026-06-05',
            'student_name' => 'Ana',
            'status' => PaymentProcedureStatus::PendingPayment->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/procedures/index')
            ->where('procedures.data.0.id', $matchingProcedure->id)
            ->where('procedures.data.0.created_at', '2026-06-05T10:30:00.000000Z')
            ->has('procedures.data', 1)
            ->where('filters.procedure_type', (string) $constancyConcept->id)
            ->where('filters.date', '2026-06-05')
            ->where('filters.student_name', 'Ana')
            ->where('filters.status', PaymentProcedureStatus::PendingPayment->value)
            ->where('filter_options.procedure_types.0.id', $constancyConcept->id)
            ->where('filter_options.statuses.1.value', PaymentProcedureStatus::PendingPayment->value));
});

test('procedure date filter uses the local finance day instead of utc date', function () {
    $assistant = financeProcedureUser();

    $lateJuneFourthProcedure = PaymentProcedure::factory()->create([
        'student_snapshot_id' => StudentSnapshot::factory()->create([
            'name' => 'NAILET GUADALUPE CORDERO UUH',
        ]),
        'status' => PaymentProcedureStatus::Paid,
        'created_at' => '2026-06-05 03:41:50',
    ]);

    $juneFifthProcedure = PaymentProcedure::factory()->create([
        'student_snapshot_id' => StudentSnapshot::factory()->create([
            'name' => 'Ana Lucia Canul',
        ]),
        'status' => PaymentProcedureStatus::Paid,
        'created_at' => '2026-06-05 05:00:00',
    ]);

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.index', [
            'date' => '2026-06-05',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('procedures.data.0.id', $juneFifthProcedure->id)
            ->has('procedures.data', 1));

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.index', [
            'date' => '2026-06-04',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('procedures.data.0.id', $lateJuneFourthProcedure->id)
            ->has('procedures.data', 1));
});

test('payment procedure requires a student and at least one active concept', function () {
    $assistant = financeProcedureUser();

    $this->actingAs($assistant)
        ->from(route('finance.payment-procedures.create'))
        ->post(route('finance.payment-procedures.store'), [
            'student' => [],
            'concept_ids' => [],
        ])
        ->assertRedirect(route('finance.payment-procedures.create'))
        ->assertSessionHasErrors(['student.siga_student_id', 'concept_ids']);
});

test('payment procedure rejects inactive concepts', function () {
    $assistant = financeProcedureUser();
    $inactiveConcept = ChargeConcept::factory()->create([
        'status' => ChargeConceptStatus::Inactive,
    ]);

    $this->actingAs($assistant)
        ->from(route('finance.payment-procedures.create'))
        ->post(route('finance.payment-procedures.store'), [
            'student' => studentPayload(),
            'concept_ids' => [$inactiveConcept->id],
        ])
        ->assertRedirect(route('finance.payment-procedures.create'))
        ->assertSessionHasErrors('concept_ids.0');
});

test('payment procedure stores student and concept snapshots with calculated total', function () {
    $assistant = financeProcedureUser();

    $internalConcept = ChargeConcept::factory()->create([
        'name' => 'Expedicion de credenciales de Educacion Normal',
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 15000,
    ]);

    $externalConcept = ChargeConcept::factory()->create([
        'name' => 'Constancias de estudios de Educacion Normal',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 8500,
    ]);

    $response = $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.store'), [
            'student' => studentPayload(),
            'concept_ids' => [$internalConcept->id, $externalConcept->id],
        ]);

    $procedure = PaymentProcedure::query()->with(['studentSnapshot', 'items'])->first();

    $response->assertRedirect(route('finance.payment-procedures.show', $procedure));

    expect($procedure->status)->toBe(PaymentProcedureStatus::PendingPayment)
        ->and($procedure->folio)->toBe('CREN-T-2026-0001')
        ->and($procedure->total_pesos)->toBe(23500)
        ->and($procedure->studentSnapshot->name)->toBe('Jose Manuel Pool')
        ->and($procedure->items)->toHaveCount(2)
        ->and($procedure->items->pluck('concept_name')->all())->toBe([
            'Expedicion de credenciales de Educacion Normal',
            'Constancias de estudios de Educacion Normal',
        ])
        ->and($procedure->items->pluck('concept_type')->all())->toEqual([
            ChargeConceptType::Internal,
            ChargeConceptType::External,
        ]);
});

test('payment procedures receive consecutive yearly audit folios', function () {
    $assistant = financeProcedureUser();
    $concept = ChargeConcept::factory()->create([
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 6500,
    ]);

    $firstResponse = $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.store'), [
            'student' => studentPayload(),
            'concept_ids' => [$concept->id],
        ]);

    $secondResponse = $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.store'), [
            'student' => [
                ...studentPayload(),
                'siga_student_id' => 'SIGA-201',
                'matricula' => '20260003',
                'name' => 'Maria Jose Can',
            ],
            'concept_ids' => [$concept->id],
        ]);

    $procedures = PaymentProcedure::query()
        ->orderBy('id')
        ->pluck('folio')
        ->all();

    $firstResponse->assertRedirect();
    $secondResponse->assertRedirect();

    expect($procedures)->toBe([
        'CREN-T-2026-0001',
        'CREN-T-2026-0002',
    ]);
});

test('pending payment procedure detail exposes payment registration action', function () {
    $assistant = financeProcedureUser();

    $procedure = PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::PendingPayment,
        'total_pesos' => 1200,
    ]);

    $this->actingAs($assistant)
        ->get(route('finance.payment-procedures.show', $procedure))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/procedures/show')
            ->where('procedure.id', $procedure->id)
            ->where('procedure.can_register_payment', true));
});

test('payment procedure uses quantity only for internal concepts that allow it', function () {
    $assistant = financeProcedureUser();

    $constancyConcept = ChargeConcept::factory()->create([
        'name' => 'Constancias internas',
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 8500,
        'allows_quantity' => true,
    ]);

    $titleConcept = ChargeConcept::factory()->create([
        'name' => 'Titulacion SEQ',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 425000,
        'allows_quantity' => false,
    ]);

    $response = $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.store'), [
            'student' => studentPayload(),
            'items' => [
                [
                    'charge_concept_id' => $constancyConcept->id,
                    'quantity' => 3,
                ],
                [
                    'charge_concept_id' => $titleConcept->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $procedure = PaymentProcedure::query()->with(['items'])->first();

    $response->assertRedirect(route('finance.payment-procedures.show', $procedure));

    expect($procedure->total_pesos)->toBe(450500)
        ->and($procedure->items)->toHaveCount(2)
        ->and($procedure->items->firstWhere('charge_concept_id', $constancyConcept->id)->quantity)->toBe(3)
        ->and($procedure->items->firstWhere('charge_concept_id', $constancyConcept->id)->subtotal_pesos)->toBe(25500)
        ->and($procedure->items->firstWhere('charge_concept_id', $titleConcept->id)->quantity)->toBe(1)
        ->and($procedure->items->firstWhere('charge_concept_id', $titleConcept->id)->subtotal_pesos)->toBe(425000);
});

test('paid procedures cannot be edited', function () {
    $assistant = financeProcedureUser();
    $procedure = PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($assistant)
        ->put(route('finance.payment-procedures.update', $procedure), [
            'concept_ids' => [],
        ])
        ->assertForbidden();
});
