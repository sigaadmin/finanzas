<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_import_file_id', 'own_revenue_activity_rule_id',
    'assignable_type', 'assignable_id', 'previous_activity_id', 'own_revenue_activity_id',
    'activity_code', 'activity_name', 'mode', 'group_key', 'group_hash', 'justification',
    'justification_note', 'assigned_by', 'assigned_at',
])]
class OwnRevenueActivityAssignment extends Model
{
    /** @use HasFactory<OwnRevenueActivityAssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => OwnRevenueActivityAssignmentMode::class,
            'justification' => OwnRevenueActivityJustification::class,
            'assigned_at' => 'datetime',
        ];
    }

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

    /** @return BelongsTo<OwnRevenueActivityRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivityRule::class, 'own_revenue_activity_rule_id');
    }

    /** @return MorphTo<Model, $this> */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function previousActivity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'previous_activity_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
