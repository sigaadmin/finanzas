<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueImportController extends Controller
{
    private const SAFE_ISSUE_CONTEXT_FIELDS = [
        'detected_year',
        'fiscal_year',
        'responsible_unit_code',
        'specific_item_code',
        'source_region',
        'normalized_region',
        'value',
        'source_cents',
        'calculated_cents',
    ];

    public function show(OwnRevenueBudget $budget): Response
    {
        Gate::authorize('viewImports', $budget);

        $session = $this->currentSession($budget);
        $files = OwnRevenueImportFile::query()
            ->whereBelongsTo($budget, 'budget')
            ->with(['issues' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->get();
        $abpreFile = $files->first(
            fn (OwnRevenueImportFile $file): bool => $file->format === OwnRevenueImportFormat::Abpre,
        );

        return Inertia::render('finance/own-revenue/imports/show', [
            'budget' => $this->budgetData($budget),
            'session' => $session === null ? null : $this->sessionData($session),
            'slots' => collect(OwnRevenueImportFormat::cases())
                ->map(fn (OwnRevenueImportFormat $format): array => [
                    'format' => $format->value,
                    'label' => $this->formatLabel($format),
                    'versions' => $files
                        ->where('format', $format)
                        ->values()
                        ->map(fn (OwnRevenueImportFile $file): array => $this->fileData($file)),
                ]),
            'unassigned_files' => $files
                ->whereNull('format')
                ->values()
                ->map(fn (OwnRevenueImportFile $file): array => $this->fileData($file)),
            'preview' => $this->previewData($abpreFile),
            'permissions' => [
                'upload' => Gate::allows('manageImports', $budget),
                'manage' => Gate::allows('manageImports', $budget),
                'confirm' => Gate::allows('confirmImports', $budget),
                'download' => Gate::allows('viewImports', $budget),
            ],
        ]);
    }

    private function currentSession(OwnRevenueBudget $budget): ?OwnRevenueImportSession
    {
        return $budget->importSessions()
            ->orderByRaw('case when status = ? then 0 else 1 end', [OwnRevenueImportSessionStatus::Open->value])
            ->latest('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function budgetData(OwnRevenueBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'fiscal_year' => $budget->fiscal_year,
            'status' => $budget->status->value,
            'institution_name' => $budget->institution_name,
            'responsible_unit_code' => $budget->responsible_unit_code,
            'responsible_unit_name' => $budget->responsible_unit_name,
            'budget_program_code' => $budget->budget_program_code,
            'budget_program_name' => $budget->budget_program_name,
            'component_code' => $budget->component_code,
            'component_name' => $budget->component_name,
            'official_activity_code' => $budget->official_activity_code,
            'official_activity_name' => $budget->official_activity_name,
            'region_code' => $budget->region_code,
            'region_name' => $budget->region_name,
            'estimated_income_cents' => $budget->estimated_income_cents === null
                ? null
                : (string) $budget->getRawOriginal('estimated_income_cents'),
        ];
    }

    /** @return array<string, mixed> */
    private function sessionData(OwnRevenueImportSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'created_at' => $session->created_at?->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function fileData(OwnRevenueImportFile $file): array
    {
        $issueCounts = $file->issues->countBy(
            fn (OwnRevenueImportIssue $issue): string => $issue->severity->value,
        );

        return [
            'id' => $file->id,
            'name' => $file->original_name,
            'size' => $file->size_bytes,
            'format' => $file->format?->value,
            'detected_format' => $file->detected_format?->value,
            'year' => $file->detected_year,
            'version' => $file->version_number,
            'status' => $file->status->value,
            'confidence' => $file->detection_confidence,
            'analyzed' => $file->analyzed_at !== null,
            'analyzed_at' => $file->analyzed_at?->toISOString(),
            'confirmed' => $file->confirmed_at !== null,
            'confirmed_at' => $file->confirmed_at?->toISOString(),
            'issue_counts' => [
                'error' => $issueCounts->get('error', 0),
                'warning' => $issueCounts->get('warning', 0),
                'info' => $issueCounts->get('info', 0),
            ],
            'issues' => $file->issues->map(fn (OwnRevenueImportIssue $issue): array => [
                'id' => $issue->id,
                'severity' => $issue->severity->value,
                'code' => $issue->code,
                'field' => $issue->field,
                'message' => $issue->message,
                'context' => $this->exactMonetaryStrings(Arr::only(
                    $issue->context ?? [],
                    self::SAFE_ISSUE_CONTEXT_FIELDS,
                )),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function previewData(?OwnRevenueImportFile $file): array
    {
        if ($file === null) {
            return $this->emptyPaginator();
        }

        return $file->rows()
            ->where('row_kind', 'abpre_line')
            ->orderBy('row_number')
            ->paginate(25, ['id', 'row_number', 'normalized_payload'], 'preview_page')
            ->through(fn (OwnRevenueImportRow $row): array => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                ...$this->exactMonetaryStrings($row->normalized_payload ?? []),
            ])
            ->toArray();
    }

    /** @return array<string, mixed> */
    private function emptyPaginator(): array
    {
        return (new LengthAwarePaginator([], 0, 25, 1))->toArray();
    }

    /** @param array<string|int, mixed> $values
     * @return array<string|int, mixed>
     */
    private function exactMonetaryStrings(array $values, bool $monetaryValues = false): array
    {
        return collect($values)->mapWithKeys(function (mixed $value, string|int $key) use ($monetaryValues): array {
            if (is_array($value)) {
                $value = $this->exactMonetaryStrings($value, $key === 'months');
            } elseif ($value !== null && ($monetaryValues || (is_string($key)
                && (str_ends_with($key, 'Cents') || str_ends_with($key, '_cents'))))) {
                $value = (string) $value;
            }

            return [$key => $value];
        })->all();
    }

    private function formatLabel(OwnRevenueImportFormat $format): string
    {
        return match ($format) {
            OwnRevenueImportFormat::Abpre => 'ABPRE',
            OwnRevenueImportFormat::WorkSheet => 'Hoja de trabajo',
            OwnRevenueImportFormat::TechnicalSheet => 'Ficha técnica',
            OwnRevenueImportFormat::Fuel => 'Combustible',
            OwnRevenueImportFormat::TravelExpenses => 'Viáticos',
        };
    }
}
