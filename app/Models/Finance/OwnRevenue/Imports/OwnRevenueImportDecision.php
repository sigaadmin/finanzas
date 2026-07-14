<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_import_issue_id', 'own_revenue_import_row_id', 'current_value', 'proposed_value',
    'resolved_value', 'resolution', 'justification', 'resolved_by', 'resolved_at',
])]
class OwnRevenueImportDecision extends Model
{
    /** @use HasFactory<OwnRevenueImportDecisionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_value' => 'array',
            'proposed_value' => 'array',
            'resolved_value' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueImportIssue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportIssue::class, 'own_revenue_import_issue_id');
    }

    /** @return BelongsTo<OwnRevenueImportRow, $this> */
    public function row(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportRow::class, 'own_revenue_import_row_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
