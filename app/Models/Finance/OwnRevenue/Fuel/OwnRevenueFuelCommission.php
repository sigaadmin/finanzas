<?php

namespace App\Models\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_fuel_fund_id', 'own_revenue_proposal_fuel_need_id', 'status',
    'commission_date', 'reason', 'route_description', 'vehicle_description',
    'kilometers', 'liters', 'amount_cents', 'effective_price_per_liter',
    'is_extraordinary', 'extraordinary_justification', 'balance_after_cents',
    'created_by', 'confirmed_by', 'confirmed_at',
])]
class OwnRevenueFuelCommission extends Model
{
    /** @use HasFactory<OwnRevenueFuelCommissionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OwnRevenueFuelCommissionStatus::class,
            'commission_date' => 'date',
            'kilometers' => 'decimal:4',
            'liters' => 'decimal:4',
            'amount_cents' => 'integer',
            'effective_price_per_liter' => 'decimal:4',
            'is_extraordinary' => 'boolean',
            'balance_after_cents' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueFuelFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueFuelFund::class, 'own_revenue_fuel_fund_id');
    }

    /** @return BelongsTo<OwnRevenueProposalFuelNeed, $this> */
    public function plannedNeed(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueProposalFuelNeed::class, 'own_revenue_proposal_fuel_need_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
