<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierDocumentStage;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\StoreExpenseDossierDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthorizeExpenseRequirementException
{
    public function __construct(private readonly StoreExpenseDossierDocument $documents) {}

    public function handle(
        OwnRevenueExpenseDossierRequirement $requirement,
        User $user,
        string $reason,
        UploadedFile $evidence,
    ): OwnRevenueExpenseDossierRequirement {
        Gate::forUser($user)->authorize('exceptExpenseRequirement', $requirement->dossier->budget);
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['exception_reason' => 'Explica el motivo de la excepción.']);
        }
        $storedPath = null;

        try {
            return DB::transaction(function () use ($requirement, $user, $reason, $evidence, &$storedPath): OwnRevenueExpenseDossierRequirement {
                $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($requirement->dossier->own_revenue_budget_id);
                Gate::forUser($user)->authorize('exceptExpenseRequirement', $budget);
                $dossier = OwnRevenueExpenseDossier::query()->whereBelongsTo($budget, 'budget')
                    ->whereKey($requirement->own_revenue_expense_dossier_id)->lockForUpdate()->firstOrFail();
                $lockedRequirement = OwnRevenueExpenseDossierRequirement::query()
                    ->whereBelongsTo($dossier, 'dossier')->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
                if ($lockedRequirement->status !== OwnRevenueExpenseRequirementStatus::Pending) {
                    throw ValidationException::withMessages(['requirement' => 'El requisito ya fue atendido.']);
                }
                $document = $this->documents->handle(
                    $dossier,
                    $user,
                    $evidence,
                    OwnRevenueExpenseDossierDocumentStage::RequirementException,
                );
                $storedPath = $document->storage_path;
                $lockedRequirement->update([
                    'status' => OwnRevenueExpenseRequirementStatus::Excepted,
                    'exception_reason' => $reason,
                    'exception_evidence_document_id' => $document->id,
                    'acted_by' => $user->id,
                    'acted_at' => now(),
                ]);

                return $lockedRequirement;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
