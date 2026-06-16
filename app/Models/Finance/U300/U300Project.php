<?php

namespace App\Models\Finance\U300;

use Database\Factories\Finance\U300\U300ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['u300_program_id', 'number', 'name', 'justification', 'sort_order'])]
class U300Project extends Model
{
    /** @use HasFactory<U300ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<U300Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(U300Program::class, 'u300_program_id');
    }

    /**
     * @return HasMany<U300Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(U300Goal::class);
    }
}
