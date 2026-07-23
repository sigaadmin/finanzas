<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class RestoreU300BackupArchive
{
    public function __construct(
        private InspectU300BackupArchive $inspector,
        private CreateU300BackupArchive $createBackup,
    ) {}

    public function handle(string $archivePath, User $restoredBy): U300Program
    {
        $preview = $this->inspector->handle($archivePath);
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('El respaldo no pudo abrirse.');
        }

        try {
            $programData = json_decode((string) $zip->getFromName('data/program.json'), true, 512, JSON_THROW_ON_ERROR);
            $sourceFilename = $programData['source_filename'] ?? null;
            $sourceContents = $sourceFilename === null
                ? null
                : $zip->getFromName('files/source/'.basename((string) $sourceFilename));
            $photos = [];

            foreach (array_keys($preview['manifest']['files'] ?? []) as $path) {
                if (str_starts_with($path, 'files/technical-sheets/')) {
                    $contents = $zip->getFromName($path);

                    if (is_string($contents)) {
                        $photos[basename($path)] = $contents;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        if (($programData['fiscal_year'] ?? null) !== $preview['fiscal_year']) {
            throw new RuntimeException('El contenido no corresponde al ejercicio del respaldo.');
        }

        U300Program::query()
            ->where('fiscal_year', $preview['fiscal_year'])
            ->get()
            ->each(fn (U300Program $program) => $this->createBackup->handle(
                $program,
                $restoredBy,
                'pre_restore',
            ));

        if (is_string($sourceContents) && is_string($programData['source_path'] ?? null)) {
            Storage::disk('local')->put($programData['source_path'], $sourceContents);
        }

        foreach ($photos as $filename => $contents) {
            Storage::disk('public')->put('u300/technical-sheets/reference-photos/'.$filename, $contents);
        }

        return DB::transaction(function () use ($preview, $programData, $restoredBy): U300Program {
            U300Program::query()->where('fiscal_year', $preview['fiscal_year'])->lockForUpdate()->get()->each->delete();

            $program = U300Program::query()->create([
                ...Arr::only($programData, [
                    'fiscal_year', 'name', 'objective', 'justification', 'requested_total_cents',
                    'approved_total_cents', 'federal_authorized_total_cents', 'responsible_name',
                    'responsible_position', 'responsible_academic_degree', 'responsible_phone',
                    'responsible_email', 'source_filename', 'source_path',
                ]),
                'imported_by' => $restoredBy->id,
            ]);

            $actions = [];

            foreach ($programData['projects'] ?? [] as $projectData) {
                $project = $program->projects()->create(Arr::only($projectData, ['number', 'name', 'justification', 'sort_order']));

                foreach ($projectData['goals'] ?? [] as $goalData) {
                    $goal = $project->goals()->create(Arr::only($goalData, ['number', 'description', 'requested_total_cents', 'approved_total_cents', 'sort_order']));

                    foreach ($goalData['actions'] ?? [] as $actionData) {
                        $action = $goal->actions()->create(Arr::only($actionData, ['number', 'name', 'justification', 'requested_total_cents', 'approved_total_cents', 'sort_order']));
                        $actions[(int) $actionData['id']] = $action;
                    }
                }
            }

            foreach ($programData['budget_versions'] ?? [] as $versionData) {
                $version = $program->budgetVersions()->create([
                    ...Arr::only($versionData, ['kind', 'name', 'status', 'total_cents']),
                    'created_by' => $restoredBy->id,
                ]);

                foreach ($versionData['requested_items'] ?? [] as $itemData) {
                    $action = $actions[(int) $itemData['u300_action_id']] ?? null;

                    if ($action !== null) {
                        $action->requestedItems()->create([
                            ...Arr::only($itemData, ['expense_concept', 'expense_item', 'period', 'quantity', 'unit_price_cents', 'total_cents', 'approved_amount_cents', 'approved_percentage', 'sort_order']),
                            'u300_budget_version_id' => $version->id,
                        ]);
                    }
                }

                foreach ($versionData['budget_lines'] ?? [] as $lineData) {
                    $action = $actions[(int) $lineData['u300_action_id']] ?? null;

                    if ($action !== null) {
                        $expenseClassificationId = null;

                        if (is_array($lineData['expense_classification'] ?? null)) {
                            $classification = ExpenseClassification::query()
                                ->where('fiscal_year', $preview['fiscal_year'])
                                ->where('specific_item_code', $lineData['expense_classification']['specific_item_code'] ?? null)
                                ->first();

                            if ($classification === null) {
                                throw new RuntimeException('El catálogo COG requerido por el respaldo no está disponible.');
                            }

                            $expenseClassificationId = $classification->id;
                        }

                        $line = $version->budgetLines()->create([
                            ...Arr::only($lineData, ['amount_cents', 'exercise_month', 'description', 'justification', 'sort_order']),
                            'u300_action_id' => $action->id,
                            'expense_classification_id' => $expenseClassificationId,
                        ]);

                        if (is_array($lineData['technical_sheet'] ?? null)) {
                            $line->technicalSheet()->create(Arr::only($lineData['technical_sheet'], [
                                'item_name', 'objective', 'work_description', 'technical_specs', 'goods_profile',
                                'beneficiaries', 'scheduled_date', 'deliverables', 'delivery_location', 'supervisor', 'payment_terms',
                            ]));
                        }

                        foreach ($lineData['movements'] ?? [] as $movementData) {
                            $line->movements()->create([
                                ...Arr::only($movementData, ['type', 'movement_date', 'concept', 'document_reference', 'amount_cents', 'cancelled_at', 'cancellation_reason']),
                                'recorded_by' => $restoredBy->id,
                                'cancelled_by' => $movementData['cancelled_at'] === null ? null : $restoredBy->id,
                            ]);
                        }
                    }
                }
            }

            return $program;
        });
    }
}
