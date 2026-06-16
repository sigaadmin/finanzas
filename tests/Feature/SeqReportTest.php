<?php

use App\Enums\Finance\PaymentTransactionStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\PaymentProcedure;
use App\Models\PaymentProcedureItem;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\StudentSnapshot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function seqReportUser(): User
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

function seqReportReceipt(ReceiptType $type, string $issuedAt, string $conceptName): Receipt
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

    $item = PaymentProcedureItem::factory()
        ->for($procedure, 'procedure')
        ->create([
            'concept_name' => $conceptName,
            'concept_type' => $type->value,
            'subtotal_pesos' => 8500,
        ]);

    $transaction = PaymentTransaction::factory()
        ->for($procedure)
        ->create([
            'status' => PaymentTransactionStatus::Paid,
            'total_pesos' => 8500,
            'paid_at' => $issuedAt,
        ]);

    return Receipt::factory()
        ->for($transaction, 'transaction')
        ->for($procedure, 'procedure')
        ->for($item, 'paymentProcedureItem')
        ->create([
            'folio' => fake()->unique()->bothify($type === ReceiptType::External ? 'EXT-######' : 'INT-######'),
            'type' => $type,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => 8500,
            'issued_at' => $issuedAt,
        ]);
}

beforeEach(function () {
    $this->withoutVite();
});

test('seq report lists only external receipts inside date filters', function () {
    $inside = seqReportReceipt(ReceiptType::External, '2026-06-10 09:00:00', 'Constancias de estudios');
    seqReportReceipt(ReceiptType::External, '2026-05-31 09:00:00', 'Examen extraordinario');
    seqReportReceipt(ReceiptType::Internal, '2026-06-10 09:00:00', 'Credencial');

    $this->actingAs(seqReportUser())
        ->get(route('finance.seq-report.index', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/reports/seq')
            ->has('rows', 1)
            ->where('rows.0.folio', $inside->folio)
            ->where('rows.0.concept_name', 'Constancias de estudios')
            ->where('totals.receipts', 1)
            ->where('totals.total_pesos', 8500)
        );
});

test('seq report export downloads the filtered excel view', function () {
    $inside = seqReportReceipt(ReceiptType::External, '2026-06-10 09:00:00', 'Constancias de estudios');
    seqReportReceipt(ReceiptType::External, '2026-07-01 09:00:00', 'Examen profesional');

    $response = $this->actingAs(seqReportUser())
        ->get(route('finance.seq-report.export', [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=seq-reporte-2026-06-01-2026-06-30.xls');

    expect($response->getContent())->toContain($inside->folio)
        ->and($response->getContent())->toContain('Constancias de estudios')
        ->and($response->getContent())->not->toContain('Examen profesional');
});
