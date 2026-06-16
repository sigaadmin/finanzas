<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreU300ImportedProject
{
    /**
     * @param  array{
     *     general: array{name: string, objective: string, justification: string, requested_total_cents: int},
     *     responsible: array{name: string, position: string, academic_degree: string, phone: string, email: string},
     *     projects: list<array{number: string, name: string, justification: string, goals: list<array{number: string, description: string, requested_total_cents: int, actions: list<array{number: string, name: string, justification: string, items: list<array{expense_concept: string, expense_item: string, period: int, quantity: int, unit_price_cents: int, total_cents: int}>}>}>
     * }  $parsed
     */
    public function handle(
        User $importedBy,
        int $fiscalYear,
        string $sourceFilename,
        string $sourcePath,
        array $parsed,
    ): U300Program {
        return DB::transaction(function () use ($importedBy, $fiscalYear, $sourceFilename, $sourcePath, $parsed): U300Program {
            $program = U300Program::create([
                'imported_by' => $importedBy->id,
                'fiscal_year' => $fiscalYear,
                'name' => $parsed['general']['name'],
                'objective' => $parsed['general']['objective'],
                'justification' => $parsed['general']['justification'],
                'requested_total_cents' => $parsed['general']['requested_total_cents'],
                'responsible_name' => $parsed['responsible']['name'],
                'responsible_position' => $parsed['responsible']['position'],
                'responsible_academic_degree' => $parsed['responsible']['academic_degree'],
                'responsible_phone' => $parsed['responsible']['phone'],
                'responsible_email' => $parsed['responsible']['email'],
                'source_filename' => $sourceFilename,
                'source_path' => $sourcePath,
            ]);

            $version = $program->budgetVersions()->create([
                'created_by' => $importedBy->id,
                'kind' => 'original_requested',
                'name' => 'Original solicitada',
                'status' => 'confirmed',
                'total_cents' => $parsed['general']['requested_total_cents'],
            ]);

            foreach ($parsed['projects'] as $projectIndex => $projectData) {
                $project = $program->projects()->create([
                    'number' => $projectData['number'],
                    'name' => $projectData['name'],
                    'justification' => $projectData['justification'],
                    'sort_order' => $projectIndex + 1,
                ]);

                foreach ($projectData['goals'] as $goalIndex => $goalData) {
                    $goal = $project->goals()->create([
                        'number' => $goalData['number'],
                        'description' => $goalData['description'],
                        'requested_total_cents' => $goalData['requested_total_cents'],
                        'sort_order' => $goalIndex + 1,
                    ]);

                    foreach ($goalData['actions'] as $actionIndex => $actionData) {
                        $action = $goal->actions()->create([
                            'number' => $actionData['number'],
                            'name' => $actionData['name'],
                            'justification' => $actionData['justification'],
                            'requested_total_cents' => collect($actionData['items'])->sum('total_cents'),
                            'sort_order' => $actionIndex + 1,
                        ]);

                        foreach ($actionData['items'] as $itemIndex => $itemData) {
                            $action->requestedItems()->create([
                                'u300_budget_version_id' => $version->id,
                                'expense_concept' => $itemData['expense_concept'],
                                'expense_item' => $itemData['expense_item'],
                                'period' => $itemData['period'],
                                'quantity' => $itemData['quantity'],
                                'unit_price_cents' => $itemData['unit_price_cents'],
                                'total_cents' => $itemData['total_cents'],
                                'sort_order' => $itemIndex + 1,
                            ]);
                        }
                    }
                }
            }

            return $program->load([
                'budgetVersions',
                'projects.goals.actions.requestedItems',
            ]);
        });
    }
}
