<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300Program;
use App\Models\Finance\U300\U300RequestedItem;
use Illuminate\Support\Facades\DB;

class UpdateU300FederalVerdict
{
    /**
     * @param  list<array{id: int, approved_amount_cents: int|null, approved_percentage?: float|null}>  $items
     */
    public function handle(U300Program $program, array $items, ?int $federalAuthorizedTotalCents = null): U300Program
    {
        return DB::transaction(function () use ($program, $items, $federalAuthorizedTotalCents): U300Program {
            $itemIds = collect($items)->pluck('id');

            $requestedItems = U300RequestedItem::query()
                ->whereIn('id', $itemIds)
                ->whereHas('action.goal.project', fn ($query) => $query
                    ->where('u300_program_id', $program->id))
                ->get()
                ->keyBy('id');

            foreach ($items as $itemData) {
                /** @var U300RequestedItem|null $requestedItem */
                $requestedItem = $requestedItems->get($itemData['id']);

                if (! $requestedItem) {
                    continue;
                }

                $requestedItem->update([
                    'approved_amount_cents' => $itemData['approved_amount_cents'],
                    'approved_percentage' => $itemData['approved_percentage'] ?? null,
                ]);
            }

            $program->load('projects.goals.actions.requestedItems');

            $programApprovedTotal = 0;

            foreach ($program->projects as $project) {
                foreach ($project->goals as $goal) {
                    $goalApprovedTotal = 0;

                    foreach ($goal->actions as $action) {
                        $actionApprovedTotal = $action->requestedItems
                            ->sum(fn (U300RequestedItem $item): int => $item->approved_amount_cents ?? 0);

                        $action->update([
                            'approved_total_cents' => $actionApprovedTotal,
                        ]);

                        $goalApprovedTotal += $actionApprovedTotal;
                    }

                    $goal->update([
                        'approved_total_cents' => $goalApprovedTotal,
                    ]);

                    $programApprovedTotal += $goalApprovedTotal;
                }
            }

            $program->update([
                'approved_total_cents' => $programApprovedTotal,
                'federal_authorized_total_cents' => $federalAuthorizedTotalCents,
            ]);

            return $program->refresh()->load('projects.goals.actions.requestedItems');
        });
    }
}
