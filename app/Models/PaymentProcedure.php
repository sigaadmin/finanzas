<?php

namespace App\Models;

use App\Enums\Finance\PaymentProcedureStatus;
use Database\Factories\PaymentProcedureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['folio', 'student_snapshot_id', 'created_by', 'status', 'total_pesos', 'paid_at', 'cancelled_at'])]
class PaymentProcedure extends Model
{
    /** @use HasFactory<PaymentProcedureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentProcedureStatus::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StudentSnapshot, $this>
     */
    public function studentSnapshot(): BelongsTo
    {
        return $this->belongsTo(StudentSnapshot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PaymentProcedureItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentProcedureItem::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
