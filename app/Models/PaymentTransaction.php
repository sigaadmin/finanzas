<?php

namespace App\Models;

use App\Enums\Finance\PaymentTransactionStatus;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payment_procedure_id', 'registered_by', 'folio', 'status', 'total_pesos', 'payment_method', 'reference', 'paid_at'])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentTransactionStatus::class,
            'paid_at' => 'datetime',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
