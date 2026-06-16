<?php

use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\OfficialFeeLinkStatus;
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

function receiptRenderingUser(): User
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

function receiptRenderingProcedure(array $attributes = []): PaymentProcedure
{
    return PaymentProcedure::factory()
        ->for(StudentSnapshot::factory()->create([
            'name' => 'Ana Maria Ku',
            'grade' => '4',
            'group' => 'C',
            'matricula' => '2022PRIM014',
            'program' => 'Licenciatura en Educacion Primaria',
        ]))
        ->create(array_merge([
            'folio' => 'CREN-T-2026-'.fake()->unique()->numberBetween(1000, 9999),
            'total_pesos' => 23500,
        ], $attributes));
}

function receiptRenderingReceipt(ReceiptType $type, array $attributes = []): Receipt
{
    $procedure = $attributes['procedure'] ?? receiptRenderingProcedure();
    unset($attributes['procedure']);

    $internalItem = PaymentProcedureItem::factory()
        ->for($procedure, 'procedure')
        ->create([
            'concept_name' => 'Expedicion de credenciales',
            'concept_type' => ChargeConceptType::Internal,
            'unit_amount_pesos' => 15000,
            'subtotal_pesos' => 15000,
        ]);

    $externalItem = PaymentProcedureItem::factory()
        ->for($procedure, 'procedure')
        ->create([
            'concept_name' => 'Constancias de estudios',
            'concept_type' => ChargeConceptType::External,
            'official_fee_link_status' => OfficialFeeLinkStatus::Linked,
            'official_fee_code' => '429',
            'official_fee_name' => 'Servicios de expedicion de constancias de estudios',
            'unit_amount_pesos' => 8500,
            'subtotal_pesos' => 8500,
        ]);

    $transaction = PaymentTransaction::factory()
        ->for($procedure)
        ->create([
            'total_pesos' => $type === ReceiptType::Internal ? 23500 : 8500,
        ]);

    return Receipt::factory()
        ->for($transaction, 'transaction')
        ->for($procedure, 'procedure')
        ->for($type === ReceiptType::Internal ? $internalItem : $externalItem, 'paymentProcedureItem')
        ->create(array_merge([
            'payment_procedure_item_id' => $type === ReceiptType::Internal ? null : $externalItem->id,
            'folio' => $type === ReceiptType::Internal ? 'INT-20260603-PRINT1' : 'EXT-20260603-SEQ001',
            'type' => $type,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => $type === ReceiptType::Internal ? 23500 : 8500,
            'amount_in_words' => $type === ReceiptType::Internal
                ? 'DOSCIENTOS TREINTA Y CINCO PESOS 00/100 M.N.'
                : 'OCHENTA Y CINCO PESOS 00/100 M.N.',
            'validation_token' => $attributes['validation_token']
                ?? ($type === ReceiptType::Internal ? 'internal-validation-token' : 'external-validation-token-'.fake()->unique()->bothify('###')),
            'issued_at' => '2026-06-03 09:00:00',
        ], $attributes));
}

beforeEach(function () {
    $this->withoutVite();
});

test('receipt index can be filtered by type status and search text', function () {
    $external = receiptRenderingReceipt(ReceiptType::External);
    receiptRenderingReceipt(ReceiptType::Internal, ['folio' => 'INT-20260603-OTHER1']);
    receiptRenderingReceipt(ReceiptType::External, [
        'folio' => 'EXT-20260603-CANCEL',
        'status' => ReceiptStatus::Cancelled,
    ]);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.index', [
            'type' => 'external',
            'status' => 'issued',
            'search' => 'Ana',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.folio', $external->folio)
            ->where('filters.type', 'external')
            ->where('filters.status', 'issued')
            ->where('filters.search', 'Ana')
        );
});

test('receipt detail includes a real qr svg for validation', function () {
    $receipt = receiptRenderingReceipt(ReceiptType::Internal);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/show')
            ->where('receipt.validation_url', route('finance.receipts.validate', 'internal-validation-token'))
            ->where('receipt.qr_svg', fn (string $svg): bool => str_contains($svg, '<svg') && str_contains($svg, '</svg>'))
        );
});

test('receipt detail exposes audit procedure folio and complete student data', function () {
    $receipt = receiptRenderingReceipt(ReceiptType::Internal, [
        'procedure' => receiptRenderingProcedure([
            'folio' => 'CREN-T-2026-0099',
        ]),
    ]);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/show')
            ->where('receipt.procedure_folio', 'CREN-T-2026-0099')
            ->where('receipt.student.matricula', '2022PRIM014')
            ->where('receipt.student.program', 'Licenciatura en Educacion Primaria')
        );
});

test('external receipt detail includes official fee concept snapshot', function () {
    $receipt = receiptRenderingReceipt(ReceiptType::External);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.show', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/show')
            ->where('receipt.item.concept_name', 'Constancias de estudios')
            ->where('receipt.item.official_fee_code', '429')
            ->where('receipt.item.official_fee_name', 'Servicios de expedicion de constancias de estudios')
        );
});

test('internal print view renders all procedure items', function () {
    $receipt = receiptRenderingReceipt(ReceiptType::Internal);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.print', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/print-internal')
            ->where('receipt.type', ReceiptType::Internal->value)
            ->has('receipt.items', 2)
            ->where('receipt.items.0.concept_name', 'Expedicion de credenciales')
            ->where('receipt.items.1.concept_name', 'Constancias de estudios')
        );
});

test('external print view renders one exact seq concept', function () {
    $receipt = receiptRenderingReceipt(ReceiptType::External);

    $this->actingAs(receiptRenderingUser())
        ->get(route('finance.receipts.print', $receipt))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/receipts/print-external-seq')
            ->where('receipt.type', ReceiptType::External->value)
            ->where('receipt.item.concept_name', 'Constancias de estudios')
            ->where('receipt.item.subtotal_pesos', 8500)
            ->where('receipt.total_pesos', 8500)
        );
});
