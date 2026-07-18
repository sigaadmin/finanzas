<?php

namespace App\Models\Finance\OwnRevenue;

use App\Models\User;
use Database\Factories\Finance\OwnRevenue\OwnRevenueBudgetClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LogicException;

#[Fillable([
    'own_revenue_budget_id', 'note', 'snapshot', 'fingerprint', 'closed_by', 'closed_at',
])]
class OwnRevenueBudgetClosure extends Model
{
    /** @use HasFactory<OwnRevenueBudgetClosureFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('El acta anual es inmutable.'));
        static::deleting(fn (): never => throw new LogicException('El acta anual es inmutable.'));
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canonicalSnapshot(): string
    {
        return json_encode(
            Arr::sortRecursive($this->snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
