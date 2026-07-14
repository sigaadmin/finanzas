<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueImportController extends Controller
{
    private const DECISIONS_PER_PAGE = 25;

    private const ISSUES_PER_PAGE = 50;

    private const VERSIONS_PER_PAGE = 10;

    private const REQUIRED_WARNING_DECISIONS = [
        'year.mismatch',
        'region.normalized',
        'abpre.annual_mismatch',
        'abpre.missing_justification',
    ];

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

    public function show(Request $request, OwnRevenueBudget $budget): Response
    {
        Gate::authorize('viewImports', $budget);

        $session = $this->currentSession($budget);
        $latestAbpreFile = $this->fileSummaryQuery($budget)
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();
        $selectedFile = $request->integer('import_file_id') > 0
            ? $this->fileSummaryQuery($budget)->find($request->integer('import_file_id'))
            : $latestAbpreFile;
        $previewFile = $selectedFile?->format === OwnRevenueImportFormat::Abpre
            ? $selectedFile
            : $latestAbpreFile;

        return Inertia::render('finance/own-revenue/imports/show', [
            'budget' => $this->budgetData($budget),
            'session' => $session === null ? null : $this->sessionData($session),
            'slots' => collect(OwnRevenueImportFormat::cases())
                ->map(fn (OwnRevenueImportFormat $format): array => $this->slotData($budget, $format)),
            ...$this->unassignedFilesData($budget),
            'selected_file' => $selectedFile === null ? null : [
                ...$this->fileData($selectedFile),
                'issues' => $this->issuesData($selectedFile),
            ],
            'preview_file' => $previewFile === null ? null : [
                'id' => $previewFile->id,
                'name' => $previewFile->original_name,
                'version' => $previewFile->version_number,
                'status' => $previewFile->status->value,
            ],
            'preview' => $this->previewData($previewFile),
            'decision_warnings' => $this->decisionWarningsData($previewFile),
            'permissions' => [
                'upload' => Gate::allows('manageImports', $budget),
                'manage' => Gate::allows('manageImports', $budget),
                'confirm' => Gate::allows('confirmImports', $budget),
                'download' => Gate::allows('viewImports', $budget),
            ],
        ]);
    }

    /** @return Builder<OwnRevenueImportFile> */
    private function fileSummaryQuery(OwnRevenueBudget $budget): Builder
    {
        return OwnRevenueImportFile::query()
            ->select([
                'id', 'own_revenue_budget_id', 'original_name', 'size_bytes', 'format', 'detected_format',
                'detected_year', 'version_number', 'status', 'detection_confidence', 'analyzed_at',
                'confirmed_at',
            ])
            ->whereBelongsTo($budget, 'budget')
            ->withCount([
                'issues as error_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Error),
                'issues as warning_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Warning),
                'issues as info_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Info),
            ]);
    }

    /** @return array<string, mixed> */
    private function slotData(OwnRevenueBudget $budget, OwnRevenueImportFormat $format): array
    {
        $history = $budget->importFiles()->where('format', $format);
        $versions = $this->fileSummaryQuery($budget)
            ->where('format', $format)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->paginate(
                self::VERSIONS_PER_PAGE,
                ['*'],
                "{$format->value}_versions_page",
            );

        return [
            'format' => $format->value,
            'label' => $this->formatLabel($format),
            'versions' => $versions->getCollection()
                ->map(fn (OwnRevenueImportFile $file): array => $this->fileData($file)),
            'versions_total' => $versions->total(),
            'versions_current_page' => $versions->currentPage(),
            'versions_last_page' => $versions->lastPage(),
            'versions_has_more' => $versions->hasMorePages(),
            'has_confirmed' => (clone $history)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('confirmed_at')
                        ->orWhere('status', OwnRevenueImportFileStatus::Confirmed);
                })
                ->exists(),
            'has_parser_pending' => (clone $history)
                ->where('status', OwnRevenueImportFileStatus::ParserPending)
                ->exists(),
            'latest_status' => (clone $history)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first(['status'])?->status->value,
        ];
    }

    /** @return array<string, mixed> */
    private function unassignedFilesData(OwnRevenueBudget $budget): array
    {
        $files = $this->fileSummaryQuery($budget)
            ->whereNull('format')
            ->orderByDesc('id')
            ->paginate(self::VERSIONS_PER_PAGE, ['*'], 'unassigned_page');

        return [
            'unassigned_files' => $files->getCollection()
                ->map(fn (OwnRevenueImportFile $file): array => $this->fileData($file)),
            'unassigned_files_meta' => [
                'total' => $files->total(),
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'has_more' => $files->hasMorePages(),
            ],
        ];
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
                'error' => (int) ($file->error_issues_count ?? 0),
                'warning' => (int) ($file->warning_issues_count ?? 0),
                'info' => (int) ($file->info_issues_count ?? 0),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function issuesData(OwnRevenueImportFile $file): array
    {
        $issues = $file->issues()
            ->select(['id', 'own_revenue_import_file_id', 'severity', 'code', 'field', 'message', 'context'])
            ->orderBy('id')
            ->paginate(self::ISSUES_PER_PAGE, ['*'], 'issues_page')
            ->through(fn (OwnRevenueImportIssue $issue): array => $this->issueData($issue));
        $data = $issues->toArray();
        $data['has_more'] = $issues->hasMorePages();

        return $data;
    }

    /** @return array<string, mixed> */
    private function decisionWarningsData(?OwnRevenueImportFile $file): array
    {
        if ($file === null) {
            $data = $this->emptyPaginator(self::DECISIONS_PER_PAGE);
            $data['has_more'] = false;

            return $data;
        }

        $warnings = $file->issues()
            ->select(['id', 'own_revenue_import_file_id', 'severity', 'code', 'field', 'message', 'context'])
            ->where('severity', OwnRevenueImportIssueSeverity::Warning)
            ->whereIn('code', self::REQUIRED_WARNING_DECISIONS)
            ->orderBy('id')
            ->paginate(self::DECISIONS_PER_PAGE, ['*'], 'decisions_page')
            ->through(fn (OwnRevenueImportIssue $issue): array => $this->issueData($issue));
        $data = $warnings->toArray();
        $data['has_more'] = $warnings->hasMorePages();

        return $data;
    }

    /** @return array<string, mixed> */
    private function issueData(OwnRevenueImportIssue $issue): array
    {
        return [
            'id' => $issue->id,
            'severity' => $issue->severity->value,
            'code' => $issue->code,
            'field' => $issue->field,
            'message' => $issue->message,
            'context' => $this->exactMonetaryStrings(Arr::only(
                $issue->context ?? [],
                self::SAFE_ISSUE_CONTEXT_FIELDS,
            )),
        ];
    }

    /** @return array<string, mixed> */
    private function previewData(?OwnRevenueImportFile $file): array
    {
        if ($file === null) {
            return $this->emptyPaginator(25);
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
    private function emptyPaginator(int $perPage): array
    {
        return (new LengthAwarePaginator([], 0, $perPage, 1))->toArray();
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
