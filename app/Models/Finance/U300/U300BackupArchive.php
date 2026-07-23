<?php

namespace App\Models\Finance\U300;

use App\Models\User;
use Database\Factories\Finance\U300\U300BackupArchiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['fiscal_year', 'kind', 'disk', 'path', 'original_filename', 'size_bytes', 'sha256', 'manifest', 'created_by'])]
class U300BackupArchive extends Model
{
    /** @use HasFactory<U300BackupArchiveFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<U300BackupOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(U300BackupOperation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['manifest' => 'array'];
    }
}
