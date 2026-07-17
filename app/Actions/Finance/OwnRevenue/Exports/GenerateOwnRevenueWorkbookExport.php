<?php

namespace App\Actions\Finance\OwnRevenue\Exports;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueWorkbookExport;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Exports\OwnRevenueWorkbookExporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GenerateOwnRevenueWorkbookExport
{
    /** @var list<string> */
    public const FORMATS = ['abpre', 'work_sheet', 'technical_sheet', 'fuel', 'travel_expenses'];

    public function __construct(private readonly OwnRevenueWorkbookExporter $exporter) {}

    public function handle(OwnRevenueBudget $budget, OwnRevenueInitialBudget $initialBudget, User $user, string $format): OwnRevenueWorkbookExport
    {
        Gate::forUser($user)->authorize('generateWorkbookExports', $budget);

        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => 'Selecciona un formato disponible.']);
        }

        return DB::transaction(function () use ($budget, $initialBudget, $user, $format): OwnRevenueWorkbookExport {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            $lockedInitialBudget = OwnRevenueInitialBudget::query()->lockForUpdate()->findOrFail($initialBudget->id);

            if ($lockedInitialBudget->own_revenue_budget_id !== $lockedBudget->id) {
                abort(404);
            }

            Gate::forUser($user)->authorize('generateWorkbookExports', $lockedBudget);

            return $this->exporter->export($lockedInitialBudget, $user, $format);
        }, attempts: 3);
    }
}
