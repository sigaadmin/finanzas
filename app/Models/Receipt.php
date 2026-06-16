<?php

namespace App\Models;

use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['payment_transaction_id', 'payment_procedure_id', 'payment_procedure_item_id', 'folio', 'type', 'status', 'total_pesos', 'amount_in_words', 'validation_token', 'issued_at', 'cancelled_at'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReceiptType::class,
            'status' => ReceiptStatus::class,
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * @return BelongsTo<PaymentProcedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(PaymentProcedure::class, 'payment_procedure_id');
    }

    /**
     * @return BelongsTo<PaymentProcedure, $this>
     */
    public function paymentProcedure(): BelongsTo
    {
        return $this->belongsTo(PaymentProcedure::class);
    }

    /**
     * @return BelongsTo<PaymentProcedureItem, $this>
     */
    public function paymentProcedureItem(): BelongsTo
    {
        return $this->belongsTo(PaymentProcedureItem::class);
    }

    /**
     * @return HasOne<ReceiptCancellation, $this>
     */
    public function cancellation(): HasOne
    {
        return $this->hasOne(ReceiptCancellation::class);
    }

    /**
     * @return HasOne<SeqDeposit, $this>
     */
    public function seqDeposit(): HasOne
    {
        return $this->hasOne(SeqDeposit::class);
    }
}
