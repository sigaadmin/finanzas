<?php

namespace App\Actions\Finance\OwnRevenue\Closing;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueBudgetClosure;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Closing\OwnRevenueAnnualCloseReview;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloseOwnRevenueBudget
{
    public function __construct(
        private readonly OwnRevenueAnnualCloseReview $review,
    ) {}

    public function handle(OwnRevenueBudget $budget, User $user, string $note): OwnRevenueBudgetClosure
    {
        Gate::forUser($user)->authorize('closeAnnualBudget', $budget);

        return DB::transaction(function () use ($budget, $user, $note): OwnRevenueBudgetClosure {
            $lockedBudget = OwnRevenueBudget::query()
                ->lockForUpdate()
                ->findOrFail($budget->id);
            Gate::forUser($user)->authorize('closeAnnualBudget', $lockedBudget);

            if ($lockedBudget->annualClosure()->exists()) {
                throw ValidationException::withMessages([
                    'closure' => 'Este ejercicio ya cuenta con un acta de cierre.',
                ]);
            }

            $cleanNote = trim($note);
            if (Str::length($cleanNote) < 10 || Str::length($cleanNote) > 1000) {
                throw ValidationException::withMessages([
                    'note' => 'La nota de cierre debe tener entre 10 y 1000 caracteres.',
                ]);
            }

            $review = $this->review->forBudget($lockedBudget);
            if (! $review['eligible']) {
                $messages = array_map(
                    static fn (array $blocker): string => 'Actualiza la revisión: '.$blocker['message'],
                    $review['blockers'],
                );

                throw ValidationException::withMessages([
                    'closure' => $messages === []
                        ? ['El estado actual del presupuesto no permite cerrarlo.']
                        : $messages,
                ]);
            }

            $snapshot = Arr::sortRecursive($review['snapshot']);
            $canonicalSnapshot = json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            $closure = $lockedBudget->annualClosure()->create([
                'note' => $cleanNote,
                'snapshot' => $snapshot,
                'fingerprint' => hash('sha256', $canonicalSnapshot),
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
            $lockedBudget->update(['status' => OwnRevenueBudgetStatus::Closed]);

            return $closure;
        }, attempts: 3);
    }
}
