<?php

namespace App\Models;

use Database\Factories\OfficialFeeConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['official_fee_schedule_id', 'code', 'name', 'amount_pesos', 'notes'])]
class OfficialFeeConcept extends Model
{
    /** @use HasFactory<OfficialFeeConceptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<OfficialFeeSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(OfficialFeeSchedule::class, 'official_fee_schedule_id');
    }

    /**
     * @return HasMany<ChargeConceptOfficialLink, $this>
     */
    public function chargeConceptLinks(): HasMany
    {
        return $this->hasMany(ChargeConceptOfficialLink::class);
    }
}
