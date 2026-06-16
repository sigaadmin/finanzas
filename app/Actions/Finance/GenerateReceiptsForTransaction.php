<?php

namespace App\Actions\Finance;

use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Models\PaymentProcedureItem;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Services\Finance\FolioService;
use App\Services\Finance\MoneyToWords;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenerateReceiptsForTransaction
{
    public function __construct(
        private readonly FolioService $folios,
        private readonly MoneyToWords $moneyToWords,
    ) {}

    /**
     * @return Collection<int, Receipt>
     */
    public function handle(PaymentTransaction $transaction): Collection
    {
        $transaction->loadMissing('procedure.items');

        $receipts = collect([
            $this->createReceipt(
                transaction: $transaction,
                type: ReceiptType::Internal,
                totalPesos: $transaction->total_pesos,
            ),
        ]);

        $externalReceipts = $transaction->procedure->items
            ->filter(fn (PaymentProcedureItem $item): bool => $item->concept_type === ChargeConceptType::External)
            ->map(fn (PaymentProcedureItem $item): Receipt => $this->createReceipt(
                transaction: $transaction,
                type: ReceiptType::External,
                totalPesos: $item->subtotal_pesos,
                item: $item,
            ));

        return $receipts->merge($externalReceipts)->values();
    }

    private function createReceipt(
        PaymentTransaction $transaction,
        ReceiptType $type,
        int $totalPesos,
        ?PaymentProcedureItem $item = null,
    ): Receipt {
        return Receipt::query()->create([
            'payment_transaction_id' => $transaction->id,
            'payment_procedure_id' => $transaction->payment_procedure_id,
            'payment_procedure_item_id' => $item?->id,
            'folio' => $this->folios->receiptFolio($type),
            'type' => $type,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => $totalPesos,
            'amount_in_words' => $this->moneyToWords->convert($totalPesos),
            'validation_token' => Str::random(48),
            'issued_at' => now(),
        ]);
    }
}
