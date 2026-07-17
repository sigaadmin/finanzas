<?php

namespace App\Models\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierDocumentStage;
use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'own_revenue_expense_dossier_id', 'stage', 'original_name', 'storage_disk',
    'storage_path', 'mime_type', 'size_bytes', 'uploaded_by', 'uploaded_at',
])]
class OwnRevenueExpenseDossierDocument extends Model
{
    /** @use HasFactory<OwnRevenueExpenseDossierDocumentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stage' => OwnRevenueExpenseDossierDocumentStage::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OwnRevenueExpenseDossier, $this> */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueExpenseDossier::class, 'own_revenue_expense_dossier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
