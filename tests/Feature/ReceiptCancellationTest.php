<?php

use App\Enums\Finance\ReceiptStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Receipt;
use App\Models\ReceiptCancellation;
use App\Models\User;

function receiptCancellationUser(UserRole $role): User
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

test('finance manager can cancel an issued receipt with an audit reason', function () {
    $receipt = Receipt::factory()->create([
        'status' => ReceiptStatus::Issued,
        'cancelled_at' => null,
    ]);

    $manager = receiptCancellationUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.receipts.cancel', $receipt), [
            'reason' => 'Folio emitido por error de captura.',
        ])
        ->assertRedirect(route('finance.receipts.show', $receipt));

    $receipt->refresh();
    $cancellation = ReceiptCancellation::query()->first();

    expect($receipt->status)->toBe(ReceiptStatus::Cancelled)
        ->and($receipt->cancelled_at)->not->toBeNull()
        ->and($cancellation->receipt->is($receipt))->toBeTrue()
        ->and($cancellation->cancelledBy->is($manager))->toBeTrue()
        ->and($cancellation->reason)->toBe('Folio emitido por error de captura.');
});

test('finance assistant cannot cancel receipts', function () {
    $receipt = Receipt::factory()->create([
        'status' => ReceiptStatus::Issued,
        'cancelled_at' => null,
    ]);

    $this->actingAs(receiptCancellationUser(UserRole::FinanceAssistant))
        ->post(route('finance.receipts.cancel', $receipt), [
            'reason' => 'Solicitud de prueba.',
        ])
        ->assertForbidden();

    expect(ReceiptCancellation::query()->count())->toBe(0)
        ->and($receipt->refresh()->status)->toBe(ReceiptStatus::Issued);
});

test('cancelled receipts cannot be cancelled again', function () {
    $receipt = Receipt::factory()->create([
        'status' => ReceiptStatus::Cancelled,
        'cancelled_at' => now(),
    ]);

    $this->actingAs(receiptCancellationUser(UserRole::FinanceManager))
        ->post(route('finance.receipts.cancel', $receipt), [
            'reason' => 'Segundo intento.',
        ])
        ->assertForbidden();
});
