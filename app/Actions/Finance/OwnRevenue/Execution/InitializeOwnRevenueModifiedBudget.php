<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Services\Finance\OwnRevenue\Imports\PortableIntegerAmount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InitializeOwnRevenueModifiedBudget
{
    public function __construct(private readonly PortableIntegerAmount $amounts) {}

    /** @return Collection<int, OwnRevenueModifiedBudgetLine> */
    public function handle(OwnRevenueInitialBudget $initialBudget): Collection
    {
        return DB::transaction(function () use ($initialBudget): Collection {
            $lockedInitialBudget = OwnRevenueInitialBudget::query()->lockForUpdate()->findOrFail($initialBudget->id);
            $budget = $lockedInitialBudget->budget()->lockForUpdate()->firstOrFail();
            $groups = data_get($lockedInitialBudget->snapshot, 'reconciliation.groups', []);
            if (! is_array($groups)) {
                throw ValidationException::withMessages([
                    'initial_budget' => 'El presupuesto inicial no contiene el detalle necesario para iniciar su ejecución.',
                ]);
            }

            $amountsByItemAndMonth = [];
            foreach ($groups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $item = (string) ($group['specific_item_code'] ?? '');
                $month = (int) ($group['month'] ?? 0);
                $amount = (string) ($group['target_amount_cents'] ?? '');
                if ($item === '' || $month < 1 || $month > 12 || ! $this->amounts->isValid($amount)) {
                    throw ValidationException::withMessages([
                        'initial_budget' => 'El presupuesto inicial contiene una distribución inválida.',
                    ]);
                }
                $key = $item.'|'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $sum = $this->amounts->add($amountsByItemAndMonth[$key]['amount'] ?? '0', $amount);
                if ($sum === null) {
                    throw ValidationException::withMessages([
                        'initial_budget' => 'El presupuesto inicial excede el importe permitido.',
                    ]);
                }
                $amountsByItemAndMonth[$key] = ['item' => $item, 'month' => $month, 'amount' => $sum];
            }

            $total = $this->amounts->sum(array_column($amountsByItemAndMonth, 'amount'));
            if ($total === null || $total !== (string) $lockedInitialBudget->getRawOriginal('total_amount_cents')) {
                throw ValidationException::withMessages([
                    'initial_budget' => 'El detalle del presupuesto inicial no coincide con su total autorizado.',
                ]);
            }

            $classifications = ExpenseClassification::query()
                ->where('fiscal_year', $budget->fiscal_year)
                ->whereIn('specific_item_code', array_column($amountsByItemAndMonth, 'item'))
                ->get()
                ->keyBy('specific_item_code');

            foreach ($amountsByItemAndMonth as $data) {
                $classification = $classifications->get($data['item']);
                if (! $classification instanceof ExpenseClassification) {
                    throw ValidationException::withMessages([
                        'initial_budget' => "La partida {$data['item']} no existe en el COG del ejercicio.",
                    ]);
                }
                $line = OwnRevenueModifiedBudgetLine::query()->firstOrCreate(
                    [
                        'own_revenue_budget_id' => $budget->id,
                        'specific_item_code' => $data['item'],
                        'month' => $data['month'],
                    ],
                    [
                        'own_revenue_initial_budget_id' => $lockedInitialBudget->id,
                        'expense_classification_id' => $classification->id,
                        'chapter_code' => $classification->chapter_code,
                        'chapter_name' => $classification->chapter_name,
                        'specific_item_name' => $classification->specific_item_name,
                        'initial_amount_cents' => $data['amount'],
                    ],
                );
                if ($line->own_revenue_initial_budget_id !== $lockedInitialBudget->id
                    || (string) $line->getRawOriginal('initial_amount_cents') !== $data['amount']) {
                    throw ValidationException::withMessages([
                        'initial_budget' => 'La ejecución ya contiene una distribución diferente al presupuesto autorizado.',
                    ]);
                }
            }

            return OwnRevenueModifiedBudgetLine::query()
                ->whereBelongsTo($budget, 'budget')
                ->orderBy('specific_item_code')
                ->orderBy('month')
                ->get();
        }, attempts: 3);
    }
}
