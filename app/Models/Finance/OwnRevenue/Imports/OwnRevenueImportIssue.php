<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['own_revenue_import_file_id', 'own_revenue_import_row_id', 'severity', 'code', 'field', 'message', 'context'])]
class OwnRevenueImportIssue extends Model
{
    /** @use HasFactory<OwnRevenueImportIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['severity' => OwnRevenueImportIssueSeverity::class, 'context' => 'array'];
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportFile::class, 'own_revenue_import_file_id');
    }

    /** @return BelongsTo<OwnRevenueImportRow, $this> */
    public function row(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportRow::class, 'own_revenue_import_row_id');
    }

    /** @return HasMany<OwnRevenueImportDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(OwnRevenueImportDecision::class);
    }

    public function hasPendingRequiredDecision(): bool
    {
        if (($this->context['requires_decision'] ?? false) !== true) {
            return false;
        }

        if (! $this->decisions()->where('resolution', 'accepted')->exists()) {
            return true;
        }

        if ($this->code !== 'work_sheet.abpre_mismatch') {
            return false;
        }

        return ! OwnRevenueImportFile::query()
            ->whereKey($this->context['abpre_import_file_id'] ?? null)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->exists();
    }
}
