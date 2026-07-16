<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_budget_id', 'version_number', 'status', 'based_on_proposal_id',
    'source_abpre_file_id', 'source_work_sheet_file_id', 'source_technical_sheet_file_id',
    'source_fuel_file_id', 'source_travel_expenses_file_id', 'source_fingerprint',
    'total_amount_cents', 'created_by', 'calculated_by', 'calculated_at',
])]
class OwnRevenueProposal extends Model
{
    /** @use HasFactory<OwnRevenueProposalFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'total_amount_cents' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OwnRevenueProposalStatus::class,
            'calculated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueProposal, $this> */
    public function basedOnProposal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_proposal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function sourceAbpreFile(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'source_abpre_file_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function sourceWorkSheetFile(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'source_work_sheet_file_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function sourceTechnicalSheetFile(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'source_technical_sheet_file_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function sourceFuelFile(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'source_fuel_file_id');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function sourceTravelExpensesFile(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'source_travel_expenses_file_id');
    }

    /** @return HasMany<OwnRevenueProposalTechnicalNeed, $this> */
    public function technicalNeeds(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalTechnicalNeed::class);
    }

    /** @return HasMany<OwnRevenueProposalFuelNeed, $this> */
    public function fuelNeeds(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalFuelNeed::class);
    }

    /** @return HasMany<OwnRevenueProposalTravelCommission, $this> */
    public function travelCommissions(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalTravelCommission::class);
    }

    /** @return HasMany<OwnRevenuePlanningCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(OwnRevenuePlanningCorrection::class);
    }

    /** @return HasMany<OwnRevenueProposalCut, $this> */
    public function cuts(): HasMany
    {
        return $this->hasMany(OwnRevenueProposalCut::class);
    }
}
