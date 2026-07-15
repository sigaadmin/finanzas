<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueActivityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'format', 'group_key', 'group_hash', 'group_payload',
    'own_revenue_activity_id', 'activity_code', 'activity_name', 'justification',
    'justification_note', 'created_by', 'is_active', 'deactivated_by', 'deactivated_at',
    'replaces_rule_id',
])]
class OwnRevenueActivityRule extends Model
{
    /** @use HasFactory<OwnRevenueActivityRuleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => OwnRevenueImportFormat::class,
            'group_payload' => 'array',
            'justification' => OwnRevenueActivityJustification::class,
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueActivity::class, 'own_revenue_activity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /** @return BelongsTo<OwnRevenueActivityRule, $this> */
    public function replacesRule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_rule_id');
    }

    /** @return HasMany<OwnRevenueActivityRule, $this> */
    public function replacementRules(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_rule_id');
    }

    /** @return HasMany<OwnRevenueActivityAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(OwnRevenueActivityAssignment::class);
    }
}
