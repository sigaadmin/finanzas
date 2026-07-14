<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignOwnRevenueImportFormat
{
    private const ALLOWED_STATUSES = [
        OwnRevenueImportFileStatus::Uploaded,
        OwnRevenueImportFileStatus::NeedsCorrection,
        OwnRevenueImportFileStatus::Failed,
        OwnRevenueImportFileStatus::ParserPending,
    ];

    public function handle(
        OwnRevenueImportFile $file,
        User $user,
        OwnRevenueImportFormat $format,
    ): OwnRevenueImportFile {
        Gate::forUser($user)->authorize('manageImports', $file->budget);

        return DB::transaction(function () use ($file, $format): OwnRevenueImportFile {
            OwnRevenueBudget::query()->lockForUpdate()->findOrFail($file->own_revenue_budget_id);
            $lockedFile = OwnRevenueImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if (! in_array($lockedFile->status, self::ALLOWED_STATUSES, true)
                || $lockedFile->confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'format' => 'El estado actual del archivo no permite corregir su formato.',
                ]);
            }

            $nextVersion = ((int) OwnRevenueImportFile::query()
                ->where('own_revenue_budget_id', $lockedFile->own_revenue_budget_id)
                ->where('format', $format)
                ->whereKeyNot($lockedFile->id)
                ->max('version_number')) + 1;

            $lockedFile->update([
                'format' => $format,
                'version_number' => $nextVersion,
                'status' => $format === OwnRevenueImportFormat::Abpre
                    ? OwnRevenueImportFileStatus::Uploaded
                    : OwnRevenueImportFileStatus::ParserPending,
                'analysis_token' => null,
            ]);

            return $lockedFile->refresh();
        }, attempts: 3);
    }
}
