<?php

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\Finance\PaymentTransactionStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use App\Models\Receipt;
use App\Models\StudentSnapshot;
use App\Models\User;

function paymentRegistrationUser(): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => UserRole::FinanceAssistant,
        'is_active' => true,
    ]);

    return $user;
}

function pendingProcedureWithConcepts(array $concepts): PaymentProcedure
{
    $procedure = PaymentProcedure::factory()
        ->for(StudentSnapshot::factory()->create([
            'name' => 'Ana Maria Ku',
            'grade' => '4',
            'group' => 'C',
        ]))
        ->create([
            'status' => PaymentProcedureStatus::PendingPayment,
            'total_pesos' => collect($concepts)->sum('amount_pesos'),
        ]);

    foreach ($concepts as $concept) {
        $procedure->items()->create([
            'charge_concept_id' => $concept->id,
            'concept_name' => $concept->name,
            'concept_type' => $concept->type,
            'unit_amount_pesos' => $concept->amount_pesos,
            'quantity' => 1,
            'subtotal_pesos' => $concept->amount_pesos,
        ]);
    }

    return $procedure->refresh();
}

test('registering a mixed payment creates one internal receipt and one external receipt per external concept', function () {
    $assistant = paymentRegistrationUser();

    $internalConcept = ChargeConcept::factory()->create([
        'name' => 'Expedicion de credenciales de Educacion Normal',
        'type' => ChargeConceptType::Internal,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 15000,
    ]);

    $externalConceptA = ChargeConcept::factory()->create([
        'name' => 'Constancias de estudios de Educacion Normal',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 8500,
    ]);

    $externalConceptB = ChargeConcept::factory()->create([
        'name' => 'Examenes profesionales de Educacion Normal',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => 120000,
    ]);

    $procedure = pendingProcedureWithConcepts([
        $internalConcept,
        $externalConceptA,
        $externalConceptB,
    ]);

    $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.payments.store', $procedure), [
            'payment_method' => 'cash',
            'reference' => 'CAJA-1',
        ])
        ->assertRedirect(route('finance.payment-procedures.show', $procedure));

    $procedure->refresh();
    $transaction = $procedure->transactions()->first();
    $receipts = Receipt::query()->orderBy('id')->get();

    expect($procedure->status)->toBe(PaymentProcedureStatus::Paid)
        ->and($transaction->status)->toBe(PaymentTransactionStatus::Paid)
        ->and($transaction->total_pesos)->toBe(143500)
        ->and($receipts)->toHaveCount(3)
        ->and($receipts->where('type', ReceiptType::Internal))->toHaveCount(1)
        ->and($receipts->where('type', ReceiptType::External))->toHaveCount(2);

    $internalReceipt = $receipts->firstWhere('type', ReceiptType::Internal);
    $externalReceipts = $receipts->where('type', ReceiptType::External)->values();

    expect($internalReceipt->total_pesos)->toBe(143500)
        ->and($internalReceipt->payment_procedure_item_id)->toBeNull()
        ->and($internalReceipt->status)->toBe(ReceiptStatus::Issued)
        ->and($internalReceipt->folio)->toBe('CREN-I-2026-0001')
        ->and($internalReceipt->validation_token)->not->toBeEmpty();

    expect($externalReceipts->pluck('total_pesos')->all())->toBe([8500, 120000])
        ->and($externalReceipts->pluck('payment_procedure_item_id')->filter()->count())->toBe(2)
        ->and($externalReceipts->pluck('folio')->all())->toBe([
            'CREN-2026-0001',
            'CREN-2026-0002',
        ])
        ->and($externalReceipts->pluck('amount_in_words')->every(fn (string $words): bool => str_contains($words, 'PESOS')))->toBeTrue();
});

test('registering an internal only payment does not create external receipts', function () {
    $assistant = paymentRegistrationUser();
    $concept = ChargeConcept::factory()->create([
        'type' => ChargeConceptType::Internal,
        'amount_pesos' => 20000,
    ]);
    $procedure = pendingProcedureWithConcepts([$concept]);

    $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.payments.store', $procedure), [
            'payment_method' => 'cash',
        ])
        ->assertRedirect(route('finance.payment-procedures.show', $procedure));

    expect(Receipt::where('type', ReceiptType::Internal)->count())->toBe(1)
        ->and(Receipt::where('type', ReceiptType::External)->count())->toBe(0);
});

test('paid procedure cannot be paid again', function () {
    $assistant = paymentRegistrationUser();
    $procedure = PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($assistant)
        ->post(route('finance.payment-procedures.payments.store', $procedure), [
            'payment_method' => 'cash',
        ])
        ->assertForbidden();
});
