<?php

namespace App\Models;

use App\Enums\Finance\OfficialFeeLinkStatus;
use Database\Factories\ChargeConceptOfficialLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['charge_concept_id', 'official_fee_concept_id', 'fiscal_year', 'status', 'notes'])]
class ChargeConceptOfficialLink extends Model
{
    /** @use HasFactory<ChargeConceptOfficialLinkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfficialFeeLinkStatus::class,
        ];
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
}
