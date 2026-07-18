<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'own_revenue_budget_id', 'own_revenue_modified_budget_line_id', 'sequence_number',
    'folio', 'status', 'concept', 'amount_cents', 'purchase_responsibility',
    'external_reference', 'purchase_reference', 'payment_request_reference',
    'finance_authorization_reference', 'budget_office_authorization_reference',
    'payment_reference', 'notes',
    'requested_by', 'sufficiency_requested_at',
    'sufficiency_confirmed_by', 'sufficiency_confirmed_at',
    'purchase_started_by', 'purchase_started_at', 'payment_requested_by', 'payment_requested_at',
    'finance_authorized_by', 'finance_authorized_at',
    'budget_office_authorized_by', 'budget_office_authorized_at', 'paid_by', 'paid_at',
])]
class OwnRevenueExpenseDossier extends Model
{
    /** @use HasFactory<OwnRevenueExpenseDossierFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'status' => OwnRevenueExpenseDossierStatus::class,
            'amount_cents' => 'integer',
            'purchase_responsibility' => OwnRevenuePurchaseResponsibility::class,
            'sufficiency_requested_at' => 'datetime',
            'sufficiency_confirmed_at' => 'datetime',
            'purchase_started_at' => 'datetime',
            'payment_requested_at' => 'datetime',
            'finance_authorized_at' => 'datetime',
            'budget_office_authorized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<OwnRevenueModifiedBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueModifiedBudgetLine::class, 'own_revenue_modified_budget_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sufficiencyConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sufficiency_confirmed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function purchaseStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchase_started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function paymentRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function financeAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_authorized_by');
    }

    /** @return BelongsTo<User, $this> */
    public function budgetOfficeAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_office_authorized_by');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return HasMany<OwnRevenueExpenseDossierTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(OwnRevenueExpenseDossierTransition::class);
    }

    /** @return HasOne<OwnRevenueExpenseDossierTransition, $this> */
    public function latestTransition(): HasOne
    {
        return $this->hasOne(OwnRevenueExpenseDossierTransition::class)->latestOfMany();
    }

    /** @return HasMany<OwnRevenueExpenseDossierDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OwnRevenueExpenseDossierDocument::class);
    }

    /** @return HasMany<OwnRevenueExpenseDossierRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(OwnRevenueExpenseDossierRequirement::class);
    }

    /** @return HasOne<OwnRevenueFuelFund, $this> */
    public function openedFuelFund(): HasOne
    {
        return $this->hasOne(OwnRevenueFuelFund::class, 'source_expense_dossier_id');
    }
}
