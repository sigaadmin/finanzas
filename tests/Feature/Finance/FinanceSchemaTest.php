<?php

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\Finance\PaymentTransactionStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use App\Models\PaymentProcedureItem;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\ReceiptCancellation;
use App\Models\SeqReportExport;
use App\Models\StudentSnapshot;
use App\Models\User;

test('finance models expose casts, relationships, and immutable payment snapshots', function () {
    $user = User::factory()->create();

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

    $student = StudentSnapshot::factory()->create([
        'siga_student_id' => 'SIGA-100',
        'name' => 'Maria Lopez Chan',
        'grade' => '2',
        'group' => 'B',
    ]);

    $procedure = PaymentProcedure::factory()
        ->for($student)
        ->for($user, 'createdBy')
        ->create([
            'status' => PaymentProcedureStatus::PendingPayment,
            'total_pesos' => 23500,
        ]);

    $internalItem = PaymentProcedureItem::factory()
        ->for($procedure)
        ->for($internalConcept, 'chargeConcept')
        ->create([
            'concept_name' => $internalConcept->name,
            'concept_type' => $internalConcept->type,
            'unit_amount_pesos' => $internalConcept->amount_pesos,
            'quantity' => 1,
            'subtotal_pesos' => $internalConcept->amount_pesos,
        ]);

    $externalItem = PaymentProcedureItem::factory()
        ->for($procedure)
        ->for($externalConcept, 'chargeConcept')
        ->create([
            'concept_name' => $externalConcept->name,
            'concept_type' => $externalConcept->type,
            'unit_amount_pesos' => $externalConcept->amount_pesos,
            'quantity' => 1,
            'subtotal_pesos' => $externalConcept->amount_pesos,
        ]);

    $transaction = PaymentTransaction::factory()
        ->for($procedure)
        ->for($user, 'registeredBy')
        ->create([
            'status' => PaymentTransactionStatus::Paid,
            'total_pesos' => 23500,
        ]);

    $internalReceipt = Receipt::factory()
        ->for($transaction)
        ->for($procedure)
        ->create([
            'type' => ReceiptType::Internal,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => 23500,
            'amount_in_words' => 'DOSCIENTOS TREINTA Y CINCO PESOS 00/100 M.N.',
        ]);

    $externalReceipt = Receipt::factory()
        ->for($transaction)
        ->for($procedure)
        ->for($externalItem, 'paymentProcedureItem')
        ->create([
            'type' => ReceiptType::External,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => 8500,
            'amount_in_words' => 'OCHENTA Y CINCO PESOS 00/100 M.N.',
        ]);

    $cancellation = ReceiptCancellation::factory()
        ->for($externalReceipt)
        ->for($user, 'cancelledBy')
        ->create();

    $export = SeqReportExport::factory()
        ->for($user, 'generatedBy')
        ->create([
            'period_month' => '2026-06',
            'filters' => ['period' => '2026-06', 'concept_type' => 'external'],
            'total_pesos' => 8500,
        ]);

    expect($procedure->studentSnapshot->is($student))->toBeTrue()
        ->and($procedure->items)->toHaveCount(2)
        ->and($procedure->status)->toBe(PaymentProcedureStatus::PendingPayment)
        ->and($internalItem->concept_type)->toBe(ChargeConceptType::Internal)
        ->and($externalItem->concept_type)->toBe(ChargeConceptType::External)
        ->and($transaction->procedure->is($procedure))->toBeTrue()
        ->and($internalReceipt->type)->toBe(ReceiptType::Internal)
        ->and($externalReceipt->paymentProcedureItem->is($externalItem))->toBeTrue()
        ->and($cancellation->receipt->is($externalReceipt))->toBeTrue()
        ->and($export->filters)->toBe(['period' => '2026-06', 'concept_type' => 'external']);

    $externalConcept->update(['name' => 'Nombre cambiado despues del pago']);

    expect($externalItem->refresh()->concept_name)->toBe('Constancias de estudios de Educacion Normal');
});
