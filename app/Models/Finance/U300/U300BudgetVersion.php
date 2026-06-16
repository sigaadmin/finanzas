<?php

namespace App\Models\Finance\U300;

use App\Models\User;
use Database\Factories\Finance\U300\U300BudgetVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['u300_program_id', 'created_by', 'kind', 'name', 'status', 'total_cents'])]
class U300BudgetVersion extends Model
{
    /** @use HasFactory<U300BudgetVersionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(U300Program::class, 'u300_program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
