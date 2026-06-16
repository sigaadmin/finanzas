<?php

namespace App\Models;

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\OfficialFeeLinkStatus;
use Database\Factories\ChargeConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'description', 'amount_pesos', 'type', 'allows_quantity', 'status', 'internal_key', 'valid_from', 'valid_until'])]
class ChargeConcept extends Model
{
    /** @use HasFactory<ChargeConceptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChargeConceptType::class,
            'allows_quantity' => 'boolean',
            'status' => ChargeConceptStatus::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return HasMany<PaymentProcedureItem, $this>
     */
    public function paymentProcedureItems(): HasMany
    {
        return $this->hasMany(PaymentProcedureItem::class);
    }

    /**
     * @return HasMany<ChargeConceptOfficialLink, $this>
     */
    public function officialLinks(): HasMany
    {
        return $this->hasMany(ChargeConceptOfficialLink::class);
    }

    /**
     * @return HasOne<ChargeConceptOfficialLink, $this>
     */
    public function currentOfficialLink(): HasOne
    {
        return $this->hasOne(ChargeConceptOfficialLink::class)
            ->where('fiscal_year', now()->year);
    }

    public function officialLinkStatusForYear(int $fiscalYear): OfficialFeeLinkStatus
    {
        return $this->officialLinks
            ->firstWhere('fiscal_year', $fiscalYear)
            ?->status ?? OfficialFeeLinkStatus::PendingReview;
    }
}
