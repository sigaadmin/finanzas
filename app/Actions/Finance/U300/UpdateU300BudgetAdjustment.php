<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300Action;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateU300BudgetAdjustment
{
    /**
     * @param  list<array{u300_action_id: int, amount_cents: int, description: string|null}>  $allocations
     */
    public function handle(U300Program $program, User $createdBy, array $allocations): U300Program
    {
        return DB::transaction(function () use ($program, $createdBy, $allocations): U300Program {
            $program->load('projects.goals.actions');
            $actionIds = collect($allocations)->pluck('u300_action_id');

            $actions = U300Action::query()
                ->whereIn('id', $actionIds)
                ->whereHas('goal.project', fn ($query) => $query
                    ->where('u300_program_id', $program->id))
                ->with('goal')
                ->get()
                ->keyBy('id');

            $allocationTotalsByGoal = collect($allocations)
                ->groupBy(fn (array $allocation): ?int => $actions
                    ->get($allocation['u300_action_id'])?->u300_goal_id)
                ->filter(fn ($group, $goalId): bool => $goalId !== null)
                ->map(fn ($group): int => $group->sum('amount_cents'));
            $allocatedTotal = collect($allocations)->sum('amount_cents');
            $adjustmentLimit = $program->federal_authorized_total_cents ?? $program->approved_total_cents ?? 0;

            if ($allocatedTotal > $adjustmentLimit) {
                throw ValidationException::withMessages([
                    'allocations' => 'La adecuación excede el monto final autorizado por la federación.',
                ]);
            }

            foreach ($program->projects as $project) {
                foreach ($project->goals as $goal) {
                    $allocated = $allocationTotalsByGoal->get($goal->id, 0);
                    $approved = $goal->approved_total_cents ?? 0;

                    if ($allocated > $approved) {
                        throw ValidationException::withMessages([
                            'allocations' => "La adecuación de la meta {$goal->number} excede la bolsa autorizada.",
                        ]);
                    }
                }
            }

            $version = $program->budgetVersions()
                ->where('kind', 'adjusted')
                ->first();

            if (! $version) {
                $version = $program->budgetVersions()->create([
                    'created_by' => $createdBy->id,
                    'kind' => 'adjusted',
                    'name' => 'Adecuación presupuestal',
                    'status' => 'draft',
                    'total_cents' => 0,
                ]);
            }

            if ($version->budgetLines()->whereHas('movements', fn ($query) => $query->whereNull('cancelled_at'))->exists()) {
                throw ValidationException::withMessages([
                    'allocations' => 'No es posible modificar la adecuación porque ya existen movimientos presupuestales activos.',
                ]);
            }

            $version->budgetLines()->delete();

            foreach ($allocations as $index => $allocation) {
                $action = $actions->get($allocation['u300_action_id']);

                if (! $action) {
                    continue;
                }

                if ($allocation['amount_cents'] === 0) {
                    continue;
                }

                $version->budgetLines()->create([
                    'u300_action_id' => $allocation['u300_action_id'],
                    'amount_cents' => $allocation['amount_cents'],
                    'description' => $allocation['description'] ?: trim($action->number.' '.$action->name),
                    'sort_order' => $index + 1,
                ]);
            }

            $version->update([
                'total_cents' => $allocatedTotal,
            ]);

            return $program->refresh()->load('budgetVersions.budgetLines', 'projects.goals.actions.budgetLines');
        });
    }
}
