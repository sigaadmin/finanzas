<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DiscardOwnRevenueImportFile
{
    public function handle(OwnRevenueImportFile $file, User $user): OwnRevenueImportFile
    {
        Gate::forUser($user)->authorize('manageImports', $file->budget);

        return DB::transaction(function () use ($file): OwnRevenueImportFile {
            OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->status === OwnRevenueImportFileStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'file' => 'No se puede descartar un archivo confirmado.',
                ]);
            }

            $lockedFile->update(['status' => OwnRevenueImportFileStatus::Discarded]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }
}
