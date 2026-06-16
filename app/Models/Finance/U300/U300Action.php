<?php

namespace App\Models\Finance\U300;

use Database\Factories\Finance\U300\U300ActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['u300_goal_id', 'number', 'name', 'justification', 'requested_total_cents', 'approved_total_cents', 'sort_order'])]
class U300Action extends Model
{
    /** @use HasFactory<U300ActionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(U300Goal::class, 'u300_goal_id');
    }

    /**
     * @return HasMany<U300RequestedItem, $this>
     */
    public function requestedItems(): HasMany
    {
        return $this->hasMany(U300RequestedItem::class);
    }

    /**
     * @return HasMany<U300BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(U300BudgetLine::class);
    }
}
