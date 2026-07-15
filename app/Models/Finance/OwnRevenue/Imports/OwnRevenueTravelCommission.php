<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueTravelCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'own_revenue_activity_id', 'source_row_id',
    'commission_date_label', 'month', 'reason', 'person_name', 'position', 'commission_days', 'destination',
    'per_diem_uma', 'lodging_uma', 'uma_value', 'per_diem_amount_cents', 'lodging_amount_cents',
    'total_amount_cents', 'flight_amount_cents', 'sort_order',
])]
class OwnRevenueTravelCommission extends Model
{
    /** @use HasFactory<OwnRevenueTravelCommissionFactory> */
    use HasFactory;

    protected $attributes = ['sort_order' => 0];

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'own_revenue_import_file_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<OwnRevenueImportRow, $this> */
    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportRow::class, 'source_row_id');
    }

    /** @return MorphMany<OwnRevenueActivityAssignment, $this> */
    public function activityAssignments(): MorphMany
    {
        return $this->morphMany(OwnRevenueActivityAssignment::class, 'assignable');
    }
}
