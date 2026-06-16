<?php

use App\Enums\Finance\ReceiptType;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\StudentSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function receiptAccessUser(): User
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

function receiptWithStudent(array $attributes = []): Receipt
{
    $procedure = PaymentProcedure::factory()
        ->for(StudentSnapshot::factory()->create([
            'name' => 'Ana Maria Ku',
            'grade' => '4',
            'group' => 'C',
        ]))
        ->create([
            'total_pesos' => 8500,
        ]);

    $transaction = PaymentTransaction::factory()
        ->for($procedure)
        ->create([
            'total_pesos' => 8500,
        ]);

    return Receipt::factory()
        ->for($transaction, 'transaction')
        ->for($procedure, 'procedure')
        ->create(array_merge([
            'folio' => 'INT-20260603-ABC123',
            'type' => ReceiptType::Internal,
            'total_pesos' => 8500,
            'amount_in_words' => 'OCHENTA Y CINCO PESOS 00/100 M.N.',
            'validation_token' => 'valid-token-123',
        ], $attributes));
}

beforeEach(function () {
    $this->withoutVite();
});

test('finance operators can view receipt detail with validation url', function () {
    $receipt = receiptWithStudent();

    $this->actingAs(receiptAccessUser())
        ->get(route('finance.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/show')
            ->where('receipt.folio', 'INT-20260603-ABC123')
            ->where('receipt.student.name', 'Ana Maria Ku')
            ->where('receipt.total_pesos', 8500)
            ->where('receipt.validation_url', route('finance.receipts.validate', $receipt->validation_token))
        );
});

test('receipt validation is public and exposes only verification data', function () {
    $receipt = receiptWithStudent([
        'folio' => 'EXT-20260603-SEQ001',
        'type' => ReceiptType::External,
        'validation_token' => 'public-validation-token',
    ]);

    $this->get(route('finance.receipts.validate', $receipt->validation_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/validate')
            ->where('receipt.folio', 'EXT-20260603-SEQ001')
            ->where('receipt.type', ReceiptType::External->value)
            ->where('receipt.student.name', 'Ana Maria Ku')
            ->where('receipt.status', 'issued')
            ->missing('receipt.validation_token')
        );
});

test('guests cannot view internal receipt detail', function () {
    $receipt = receiptWithStudent();

    $this->get(route('finance.receipts.show', $receipt))
        ->assertRedirect(route('login'));
});
