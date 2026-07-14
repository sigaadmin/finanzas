<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['own_revenue_budget_id', 'created_by', 'status', 'completed_at'])]
class OwnRevenueImportSession extends Model
{
    /** @use HasFactory<OwnRevenueImportSessionFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['status' => OwnRevenueImportSessionStatus::class, 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<OwnRevenueImportFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(OwnRevenueImportFile::class);
    }
}
