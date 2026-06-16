<?php

namespace App\Models;

use App\Enums\Finance\OfficialFeeScheduleStatus;
use Database\Factories\OfficialFeeScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['fiscal_year', 'source_name', 'source_url', 'published_on', 'status', 'notes'])]
class OfficialFeeSchedule extends Model
{
    /** @use HasFactory<OfficialFeeScheduleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'status' => OfficialFeeScheduleStatus::class,
        ];
    }

    /**
     * @return HasMany<OfficialFeeConcept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(OfficialFeeConcept::class);
    }
}
