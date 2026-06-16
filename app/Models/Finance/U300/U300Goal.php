<?php

namespace App\Models\Finance\U300;

use Database\Factories\Finance\U300\U300GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['u300_project_id', 'number', 'description', 'requested_total_cents', 'approved_total_cents', 'sort_order'])]
class U300Goal extends Model
{
    /** @use HasFactory<U300GoalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(U300Project::class, 'u300_project_id');
    }

    /**
     * @return HasMany<U300Action, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(U300Action::class);
    }
}
