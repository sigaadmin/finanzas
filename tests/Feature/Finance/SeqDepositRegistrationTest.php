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
use App\Models\PaymentProcedureItem;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\SeqDeposit;
use App\Models\StudentSnapshot;
use App\Models\User;

function seqDepositUser(UserRole $role = UserRole::FinanceManager): User
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

function externalReceiptForSeqDeposit(int $amountPesos = 1200): Receipt
{
    $concept = ChargeConcept::factory()->create([
        'name' => 'Examen profesional',
        'type' => ChargeConceptType::External,
        'status' => ChargeConceptStatus::Active,
        'amount_pesos' => $amountPesos,
    ]);

    $procedure = PaymentProcedure::factory()
        ->for(StudentSnapshot::factory()->create([
            'name' => 'Ana Maria Ku',
            'grade' => '4',
            'group' => 'C',
        ]))
        ->create([
            'status' => PaymentProcedureStatus::Paid,
            'total_pesos' => $amountPesos,
            'paid_at' => now(),
        ]);

    $item = PaymentProcedureItem::factory()
        ->for($procedure)
        ->for($concept, 'chargeConcept')
        ->create([
            'concept_name' => $concept->name,
            'concept_type' => ChargeConceptType::External,
            'unit_amount_pesos' => $amountPesos,
            'quantity' => 1,
            'subtotal_pesos' => $amountPesos,
        ]);

    $transaction = PaymentTransaction::factory()
        ->for($procedure, 'procedure')
        ->create([
            'status' => PaymentTransactionStatus::Paid,
            'total_pesos' => $amountPesos,
            'paid_at' => now(),
        ]);

    return Receipt::factory()
        ->for($transaction, 'transaction')
        ->for($procedure, 'paymentProcedure')
        ->for($item, 'paymentProcedureItem')
        ->create([
            'type' => ReceiptType::External,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => $amountPesos,
        ]);
}

test('finance manager can register one seq deposit for an external receipt with matching pesos amount', function () {
    $manager = seqDepositUser();
    $receipt = externalReceiptForSeqDeposit(1200);

    $this->actingAs($manager)
        ->post(route('finance.receipts.seq-deposits.store', $receipt), [
            'deposit_date' => '2026-06-04',
            'bank_transaction_folio' => 'BBVA-98421',
            'deposit_type' => 'practicaja',
            'deposit_concept' => 'Examen profesional',
            'amount_pesos' => 1200,
            'notes' => 'Deposito individual para recibo externo.',
        ])
        ->assertRedirect(route('finance.payment-procedures.show', $receipt->paymentProcedure));

    $deposit = SeqDeposit::query()->first();

    expect($deposit)->not->toBeNull()
        ->and($deposit->receipt_id)->toBe($receipt->id)
        ->and($deposit->registered_by)->toBe($manager->id)
        ->and($deposit->amount_pesos)->toBe(1200)
        ->and($deposit->bank_transaction_folio)->toBe('BBVA-98421');
});

test('seq deposit amount must match the external receipt amount exactly', function () {
    $manager = seqDepositUser();
    $receipt = externalReceiptForSeqDeposit(1200);

    $this->actingAs($manager)
        ->from(route('finance.payment-procedures.show', $receipt->paymentProcedure))
        ->post(route('finance.receipts.seq-deposits.store', $receipt), [
            'deposit_date' => '2026-06-04',
            'bank_transaction_folio' => 'BBVA-98421',
            'deposit_type' => 'ventanilla',
            'deposit_concept' => 'Examen profesional',
            'amount_pesos' => 1300,
        ])
        ->assertRedirect(route('finance.payment-procedures.show', $receipt->paymentProcedure))
        ->assertSessionHasErrors('amount_pesos');

    expect(SeqDeposit::query()->exists())->toBeFalse();
});

test('seq deposit can only be registered for external receipts once', function () {
    $manager = seqDepositUser();
    $receipt = externalReceiptForSeqDeposit(1200);

    SeqDeposit::factory()->for($receipt)->create([
        'registered_by' => $manager->id,
        'amount_pesos' => 1200,
    ]);

    $this->actingAs($manager)
        ->post(route('finance.receipts.seq-deposits.store', $receipt), [
            'deposit_date' => '2026-06-04',
            'bank_transaction_folio' => 'BBVA-99999',
            'deposit_type' => 'ventanilla',
            'deposit_concept' => 'Examen profesional',
            'amount_pesos' => 1200,
        ])
        ->assertForbidden();
});
