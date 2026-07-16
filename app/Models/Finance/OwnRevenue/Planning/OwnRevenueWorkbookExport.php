<?php

namespace App\Models\Finance\OwnRevenue\Planning;

use App\Models\User;
use Database\Factories\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['own_revenue_initial_budget_id', 'format', 'storage_disk', 'storage_path', 'file_name', 'sha256', 'total_amount_cents', 'generated_by', 'generated_at'])]
class OwnRevenueWorkbookExport extends Model
{
    /** @use HasFactory<OwnRevenueWorkbookExportFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    /** @return BelongsTo<OwnRevenueInitialBudget, $this> */
    public function initialBudget(): BelongsTo
    {
        return $this->belongsTo(OwnRevenueInitialBudget::class, 'own_revenue_initial_budget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
