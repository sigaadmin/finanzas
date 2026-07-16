<?php

namespace App\Models\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\OwnRevenueBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by',
    'fiscal_year',
    'status',
    'institution_name',
    'responsible_unit_code',
    'responsible_unit_name',
    'budget_program_code',
    'budget_program_name',
    'component_code',
    'component_name',
    'official_activity_code',
    'official_activity_name',
    'region_code',
    'region_name',
    'estimated_income_cents',
    'cut_percentage',
    'uma_value',
    'uma_status',
    'fuel_price_per_liter',
    'fuel_price_status',
    'fuel_budget_month',
    'cog_source_year',
    'cog_status',
    'cog_confirmed_by',
    'cog_confirmed_at',
])]
class OwnRevenueBudget extends Model
{
    /** @use HasFactory<OwnRevenueBudgetFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'region_code' => '02-001',
        'region_name' => 'Felipe Carrillo Puerto',
        'uma_status' => 'pending_review',
        'fuel_price_status' => 'pending_review',
        'fuel_budget_month' => 4,
        'cog_status' => 'pending_confirmation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OwnRevenueBudgetStatus::class,
            'cut_percentage' => 'decimal:2',
            'uma_value' => 'decimal:4',
            'uma_status' => AnnualValueStatus::class,
            'fuel_price_per_liter' => 'decimal:4',
            'fuel_price_status' => AnnualValueStatus::class,
            'cog_status' => CogCatalogStatus::class,
            'cog_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cogConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cog_confirmed_by');
    }

    /**
     * @return HasMany<OwnRevenueActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(OwnRevenueActivity::class);
    }

    /**
     * @return HasMany<OwnRevenueSignatory, $this>
     */
    public function signatories(): HasMany
    {
        return $this->hasMany(OwnRevenueSignatory::class);
    }

    /** @return HasMany<OwnRevenueImportSession, $this> */
    public function importSessions(): HasMany
    {
        return $this->hasMany(OwnRevenueImportSession::class);
    }

    /** @return HasMany<OwnRevenueImportFile, $this> */
    public function importFiles(): HasMany
    {
        return $this->hasMany(OwnRevenueImportFile::class);
    }

    /** @return HasMany<OwnRevenueAbpreLine, $this> */
    public function abpreLines(): HasMany
    {
        return $this->hasMany(OwnRevenueAbpreLine::class);
    }

    /** @return HasMany<OwnRevenueWorkSheetLine, $this> */
    public function workSheetLines(): HasMany
    {
        return $this->hasMany(OwnRevenueWorkSheetLine::class);
    }

    /** @return HasMany<OwnRevenueTechnicalSheetNeed, $this> */
    public function technicalSheetNeeds(): HasMany
    {
        return $this->hasMany(OwnRevenueTechnicalSheetNeed::class);
    }

    /** @return HasMany<OwnRevenueFuelPlan, $this> */
    public function fuelPlans(): HasMany
    {
        return $this->hasMany(OwnRevenueFuelPlan::class);
    }

    /** @return HasMany<OwnRevenueTravelCommission, $this> */
    public function travelCommissions(): HasMany
    {
        return $this->hasMany(OwnRevenueTravelCommission::class);
    }

    /** @return HasMany<OwnRevenueActivityRule, $this> */
    public function activityRules(): HasMany
    {
        return $this->hasMany(OwnRevenueActivityRule::class);
    }

    /** @return HasMany<OwnRevenueActivityAssignment, $this> */
    public function activityAssignments(): HasMany
    {
        return $this->hasMany(OwnRevenueActivityAssignment::class);
    }

    /** @return HasMany<OwnRevenueProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(OwnRevenueProposal::class);
    }

    /** @return HasMany<OwnRevenueInitialBudget, $this> */
    public function initialBudgets(): HasMany
    {
        return $this->hasMany(OwnRevenueInitialBudget::class);
    }

    /** @return HasMany<OwnRevenueRoute, $this> */
    public function planningRoutes(): HasMany
    {
        return $this->hasMany(OwnRevenueRoute::class);
    }

    /** @return HasMany<OwnRevenueTravelDestination, $this> */
    public function travelDestinations(): HasMany
    {
        return $this->hasMany(OwnRevenueTravelDestination::class);
    }

    /** @return HasMany<OwnRevenueTravelRate, $this> */
    public function travelRates(): HasMany
    {
        return $this->hasMany(OwnRevenueTravelRate::class);
    }
}
