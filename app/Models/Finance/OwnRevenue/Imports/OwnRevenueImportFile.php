<?php

namespace App\Models\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Imports\OwnRevenueImportFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'own_revenue_import_session_id', 'own_revenue_budget_id', 'uploaded_by', 'format', 'detected_format',
    'detected_year', 'original_name', 'storage_disk', 'storage_path', 'size_bytes', 'sha256', 'version_number',
    'status', 'analysis_token', 'detection_confidence', 'detection_evidence', 'budget_updated_at_at_analysis', 'analyzed_at',
    'confirmed_by', 'confirmed_at', 'replaced_by_file_id',
])]
class OwnRevenueImportFile extends Model
{
    /** @use HasFactory<OwnRevenueImportFileFactory> */
    use HasFactory;

    protected $attributes = ['storage_disk' => 'local', 'status' => 'uploaded'];

    protected function casts(): array
    {
        return [
            'format' => OwnRevenueImportFormat::class,
            'detected_format' => OwnRevenueImportFormat::class,
            'status' => OwnRevenueImportFileStatus::class,
            'detection_evidence' => 'array',
            'budget_updated_at_at_analysis' => 'datetime',
            'analyzed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueImportSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueImportSession::class, 'own_revenue_import_session_id');
    }

    /** @return BelongsTo<OwnRevenueBudget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueBudget::class, 'own_revenue_budget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<OwnRevenueImportFile, $this> */
    public function replacedByFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_file_id');
    }

    /** @return HasMany<OwnRevenueImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(OwnRevenueImportRow::class);
    }

    /** @return HasMany<OwnRevenueImportIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(OwnRevenueImportIssue::class);
    }
}
