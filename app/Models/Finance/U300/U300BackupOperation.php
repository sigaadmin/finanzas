<?php

namespace App\Models\Finance\U300;

use App\Models\User;
use Database\Factories\Finance\U300\U300BackupOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['u300_backup_archive_id', 'fiscal_year', 'type', 'status', 'performed_by', 'details', 'failure_reason'])]
class U300BackupOperation extends Model
{
    /** @use HasFactory<U300BackupOperationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<U300BackupArchive, $this> */
    public function archive(): BelongsTo
    {
        return $this->belongsTo(U300BackupArchive::class, 'u300_backup_archive_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
