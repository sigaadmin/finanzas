<?php

namespace App\Models;

use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\OfficialFeeLinkStatus;
use Database\Factories\PaymentProcedureItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payment_procedure_id', 'charge_concept_id', 'official_fee_concept_id', 'concept_name', 'concept_type', 'official_fee_fiscal_year', 'official_fee_link_status', 'official_fee_code', 'official_fee_name', 'official_fee_amount_pesos', 'unit_amount_pesos', 'quantity', 'subtotal_pesos'])]
class PaymentProcedureItem extends Model
{
    /** @use HasFactory<PaymentProcedureItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concept_type' => ChargeConceptType::class,
            'official_fee_link_status' => OfficialFeeLinkStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentProcedureItem $item): void {
            if ($item->official_fee_link_status !== null) {
                return;
            }

            $chargeConcept = $item->chargeConcept ?? ChargeConcept::query()->find($item->charge_concept_id);
            $link = $chargeConcept?->currentOfficialLink()->with('officialFeeConcept')->first();

            $item->official_fee_concept_id = $link?->official_fee_concept_id;
            $item->official_fee_fiscal_year = $link?->fiscal_year ?? now()->year;
            $item->official_fee_link_status = $link?->status ?? OfficialFeeLinkStatus::PendingReview;
            $item->official_fee_code = $link?->officialFeeConcept?->code;
            $item->official_fee_name = $link?->officialFeeConcept?->name;
            $item->official_fee_amount_pesos = $link?->officialFeeConcept?->amount_pesos;
        });
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
     * @return BelongsTo<ChargeConcept, $this>
     */
    public function chargeConcept(): BelongsTo
    {
        return $this->belongsTo(ChargeConcept::class);
    }

    /**
     * @return BelongsTo<OfficialFeeConcept, $this>
     */
    public function officialFeeConcept(): BelongsTo
    {
        return $this->belongsTo(OfficialFeeConcept::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
