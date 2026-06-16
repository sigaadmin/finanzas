<?php

use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\PaymentProcedure;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

function financeNavigationUser(): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    return $user;
}

beforeEach(function () {
    $this->withoutVite();
    Date::setTestNow('2026-06-03 10:00:00');
});

afterEach(function () {
    Date::setTestNow();
});

test('finance dashboard exposes operational counts', function () {
    PaymentProcedure::factory()->count(2)->create([
        'status' => PaymentProcedureStatus::PendingPayment,
    ]);

    PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::Paid,
        'paid_at' => '2026-06-03 09:00:00',
    ]);

    PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::Paid,
        'paid_at' => '2026-06-02 09:00:00',
    ]);

    Receipt::factory()->count(3)->create([
        'status' => ReceiptStatus::Issued,
        'issued_at' => '2026-06-03 09:00:00',
    ]);

    Receipt::factory()->count(2)->create([
        'type' => ReceiptType::External,
        'status' => ReceiptStatus::Issued,
        'issued_at' => '2026-06-15 09:00:00',
    ]);

    Receipt::factory()->create([
        'type' => ReceiptType::External,
        'status' => ReceiptStatus::Cancelled,
        'issued_at' => '2026-06-15 09:00:00',
    ]);

    Receipt::factory()->create([
        'type' => ReceiptType::External,
        'status' => ReceiptStatus::Issued,
        'issued_at' => '2026-05-31 09:00:00',
    ]);

    $this->actingAs(financeNavigationUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('dashboard')
            ->where('metrics.pending_procedures', 2)
            ->where('metrics.paid_today', 1)
            ->where('metrics.receipts_issued_today', 3)
            ->where('metrics.external_receipts_this_month', 2)
        );
});
