<?php

namespace App\Models\Finance\U300;

use App\Models\User;
use Database\Factories\Finance\U300\U300ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'imported_by',
    'fiscal_year',
    'name',
    'objective',
    'justification',
    'requested_total_cents',
    'approved_total_cents',
    'federal_authorized_total_cents',
    'responsible_name',
    'responsible_position',
    'responsible_academic_degree',
    'responsible_phone',
    'responsible_email',
    'source_filename',
    'source_path',
])]
class U300Program extends Model
{
    /** @use HasFactory<U300ProgramFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return HasMany<U300BudgetVersion, $this>
     */
    public function budgetVersions(): HasMany
    {
        return $this->hasMany(U300BudgetVersion::class);
    }

    /**
     * @return HasMany<U300Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(U300Project::class);
    }
}
