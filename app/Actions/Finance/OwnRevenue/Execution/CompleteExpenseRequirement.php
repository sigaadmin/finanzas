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

class CompleteExpenseRequirement
{
    public function __construct(private readonly StoreExpenseDossierDocument $documents) {}

    public function handle(
        OwnRevenueExpenseDossierRequirement $requirement,
        User $user,
        string $notes,
        ?UploadedFile $evidence,
    ): OwnRevenueExpenseDossierRequirement {
        Gate::forUser($user)->authorize('completeExpenseRequirement', $requirement->dossier->budget);
        $notes = trim($notes);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($requirement, $user, $notes, $evidence, &$storedPath): OwnRevenueExpenseDossierRequirement {
                $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($requirement->dossier->own_revenue_budget_id);
                Gate::forUser($user)->authorize('completeExpenseRequirement', $budget);
                $dossier = OwnRevenueExpenseDossier::query()->whereBelongsTo($budget, 'budget')
                    ->whereKey($requirement->own_revenue_expense_dossier_id)->lockForUpdate()->firstOrFail();
                $lockedRequirement = OwnRevenueExpenseDossierRequirement::query()->with('rule')
                    ->whereBelongsTo($dossier, 'dossier')->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
                if ($lockedRequirement->status !== OwnRevenueExpenseRequirementStatus::Pending) {
                    throw ValidationException::withMessages(['requirement' => 'El requisito ya fue atendido.']);
                }
                if ($lockedRequirement->rule->requires_evidence && $evidence === null) {
                    throw ValidationException::withMessages(['evidence' => 'Adjunta la evidencia requerida.']);
                }

                $document = $evidence === null ? null : $this->documents->handle(
                    $dossier,
                    $user,
                    $evidence,
                    OwnRevenueExpenseDossierDocumentStage::RequirementEvidence,
                );
                $storedPath = $document?->storage_path;
                $lockedRequirement->update([
                    'status' => OwnRevenueExpenseRequirementStatus::Completed,
                    'notes' => $notes === '' ? null : $notes,
                    'evidence_document_id' => $document?->id,
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
