<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartOwnRevenueImportSession
{
    public function handle(OwnRevenueBudget $budget, User $user): OwnRevenueImportSession
    {
        Gate::forUser($user)->authorize('manageImports', $budget);

        return DB::transaction(function () use ($budget, $user): OwnRevenueImportSession {
            OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);

            return OwnRevenueImportSession::query()
                ->whereBelongsTo($budget, 'budget')
                ->where('status', OwnRevenueImportSessionStatus::Open)
                ->lockForUpdate()
                ->first()
                ?? OwnRevenueImportSession::query()->create([
                    'own_revenue_budget_id' => $budget->id,
                    'created_by' => $user->id,
                    'status' => OwnRevenueImportSessionStatus::Open,
                ]);
        }, attempts: 3);
    }
}
