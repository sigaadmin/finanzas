<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Actions\Finance\OwnRevenue\Imports\AssignOwnRevenueImportFormat;
use App\Actions\Finance\OwnRevenue\Imports\CaptureOwnRevenueImportAnalysisSnapshot;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class OwnRevenueImportViewData
{
    /** @var array<int, bool> */
    private array $workSheetAbpreCurrent = [];

    private const DECISIONS_PER_PAGE = 25;

    private const ISSUES_PER_PAGE = 50;

    private const PREVIEW_PER_PAGE = 25;

    public function __construct(
        private readonly CaptureOwnRevenueImportAnalysisSnapshot $captureSnapshot,
    ) {}

    private const REQUIRED_WARNING_DECISIONS = [
        'year.mismatch',
        'region.normalized',
        'abpre.annual_mismatch',
        'abpre.missing_justification',
        'work_sheet.abpre_mismatch',
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
        'work_sheet_total_cents',
        'abpre_total_cents',
        'difference_cents',
        'abpre_import_file_id',
        'work_sheet_source_rows',
        'abpre_line_ids',
        'requires_decision',
        'requires_reanalysis',
    ];

    /** @return array<string, mixed> */
    public function budget(OwnRevenueBudget $budget): array
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
    public function file(OwnRevenueImportFile $file): array
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
            'analysis_revision' => $file->analysis_revision,
            'confirmed' => $file->confirmed_at !== null,
            'confirmed_at' => $file->confirmed_at?->toISOString(),
            'can_reclassify' => AssignOwnRevenueImportFormat::canReclassify($file),
            'issue_counts' => [
                'error' => (int) ($file->error_issues_count ?? 0),
                'warning' => (int) ($file->warning_issues_count ?? 0),
                'info' => (int) ($file->info_issues_count ?? 0),
            ],
        ];
    }

    /** @return array<string, Closure> */
    public function issueCounts(): array
    {
        return [
            'issues as error_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Error),
            'issues as warning_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Warning),
            'issues as info_issues_count' => fn ($query) => $query->where('severity', OwnRevenueImportIssueSeverity::Info),
        ];
    }

    /** @return array<string, mixed> */
    public function issues(OwnRevenueImportFile $file): array
    {
        $issues = $this->paginateClamped(
            $file->issues()
                ->select(['id', 'own_revenue_import_file_id', 'severity', 'code', 'field', 'message', 'context'])
                ->orderBy('id'),
            self::ISSUES_PER_PAGE,
            'issues_page',
        )
            ->through(fn (OwnRevenueImportIssue $issue): array => $this->issue($issue));
        $data = $issues->toArray();
        $data['has_more'] = $issues->hasMorePages();

        return $data;
    }

    /** @return array<string, mixed> */
    public function decisionWarnings(OwnRevenueImportFile $file): array
    {
        $warnings = $this->paginateClamped(
            $file->issues()
                ->select(['id', 'own_revenue_import_file_id', 'severity', 'code', 'field', 'message', 'context'])
                ->where('severity', OwnRevenueImportIssueSeverity::Warning)
                ->whereIn('code', self::REQUIRED_WARNING_DECISIONS)
                ->orderBy('id'),
            self::DECISIONS_PER_PAGE,
            'decisions_page',
        )
            ->through(fn (OwnRevenueImportIssue $issue): array => $this->issue($issue));
        $data = $warnings->toArray();
        $data['has_more'] = $warnings->hasMorePages();

        return $data;
    }

    /** @return array<string, mixed> */
    public function emptyDecisionWarnings(): array
    {
        $data = $this->emptyPaginator(self::DECISIONS_PER_PAGE);
        $data['has_more'] = false;

        return $data;
    }

    /** @return array<string, mixed> */
    public function preview(OwnRevenueImportFile $file): array
    {
        return $this->paginateClamped(
            $file->rows()
                ->where('row_kind', 'abpre_line')
                ->orderBy('row_number'),
            self::PREVIEW_PER_PAGE,
            'preview_page',
            ['id', 'row_number', 'normalized_payload'],
        )
            ->through(fn (OwnRevenueImportRow $row): array => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                ...$this->exactMonetaryStrings($row->normalized_payload ?? []),
            ])
            ->toArray();
    }

    /** @return array<string, mixed> */
    public function workSheetPreview(OwnRevenueImportFile $file): array
    {
        $preview = $this->paginateClamped(
            $file->rows()
                ->where('row_kind', 'work_sheet_normalized_line')
                ->orderBy('row_number'),
            self::PREVIEW_PER_PAGE,
            'preview_page',
            ['id', 'row_number', 'normalized_payload'],
        );
        $itemCodes = $preview->getCollection()
            ->map(fn (OwnRevenueImportRow $row): string => (string) ($row->normalized_payload['specificItemCode'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $reconciliation = $this->workSheetReconciliation($file, $itemCodes);

        return $preview
            ->through(function (OwnRevenueImportRow $row) use ($reconciliation): array {
                $payload = $this->exactMonetaryStrings($row->normalized_payload ?? []);
                $specificItemCode = (string) ($payload['specificItemCode'] ?? '');
                $sourceRegions = collect($payload['sourceRegions'] ?? [])
                    ->filter(fn (mixed $region): bool => is_array($region)
                        && is_string($region['code'] ?? null)
                        && is_string($region['name'] ?? null))
                    ->unique(fn (array $region): string => $region['code'].'|'.$region['name'])
                    ->values()
                    ->all();

                return [
                    'id' => $row->id,
                    'row_number' => $row->row_number,
                    ...$payload,
                    'sourceRegions' => $sourceRegions,
                    'abpreAmountCents' => $reconciliation[$specificItemCode]['abpre'] ?? '0',
                    'differenceCents' => $reconciliation[$specificItemCode]['difference'] ?? '0',
                ];
            })
            ->toArray();
    }

    /** @return array<string, mixed> */
    public function workSheetIssues(OwnRevenueImportFile $file, bool $blocking): array
    {
        $severity = $blocking
            ? OwnRevenueImportIssueSeverity::Error
            : OwnRevenueImportIssueSeverity::Warning;
        $pageName = $blocking ? 'blocking_page' : 'review_page';
        $abpreIsCurrent = $this->workSheetAbpreIsCurrent($file);

        return $this->paginateClamped(
            $file->issues()
                ->select(['id', 'own_revenue_import_file_id', 'severity', 'message', 'context'])
                ->with(['decisions' => fn ($query) => $query
                    ->select([
                        'id', 'own_revenue_import_issue_id', 'resolution', 'justification',
                        'resolved_at',
                    ])
                    ->latest('resolved_at')
                    ->latest('id')])
                ->where('severity', $severity)
                ->orderBy('id'),
            self::ISSUES_PER_PAGE,
            $pageName,
        )
            ->through(function (OwnRevenueImportIssue $issue) use ($abpreIsCurrent): array {
                $context = $this->exactMonetaryStrings($issue->context ?? []);
                $decision = $abpreIsCurrent
                    ? $issue->decisions->first()
                    : null;

                return [
                    'id' => $issue->id,
                    'severity' => $issue->severity->value,
                    'message' => $issue->message,
                    'item_code' => $context['specific_item_code'] ?? null,
                    'work_sheet_amount_cents' => $context['work_sheet_total_cents'] ?? null,
                    'abpre_amount_cents' => $context['abpre_total_cents'] ?? null,
                    'difference_cents' => $context['difference_cents'] ?? null,
                    'requires_decision' => ($context['requires_decision'] ?? false) === true,
                    'decision' => $decision === null ? null : [
                        'status' => $decision->resolution,
                        'justification' => $decision->justification,
                    ],
                ];
            })
            ->toArray();
    }

    public function workSheetViewState(OwnRevenueImportFile $file): string
    {
        if ($file->status === OwnRevenueImportFileStatus::Confirmed) {
            return 'confirmed';
        }

        if ($file->status === OwnRevenueImportFileStatus::Analyzing) {
            return 'analyzing';
        }

        if ($file->status === OwnRevenueImportFileStatus::Failed) {
            return 'failed';
        }

        if ($file->analyzed_at === null) {
            return 'not_analyzed';
        }

        if ($file->abpre_import_file_id_at_analysis !== null
            && ! $this->workSheetAbpreIsCurrent($file)) {
            return 'abpre_changed';
        }

        return $file->rows()->where('row_kind', 'work_sheet_normalized_line')->exists()
            ? 'ready'
            : 'empty';
    }

    public function workSheetDecisionsAreCurrent(OwnRevenueImportFile $file): bool
    {
        return $this->workSheetAbpreIsCurrent($file);
    }

    /** @return array{can_confirm: bool, reasons: list<string>} */
    public function workSheetConfirmationState(OwnRevenueImportFile $file): array
    {
        if ($file->status === OwnRevenueImportFileStatus::Confirmed || $file->confirmed_at !== null) {
            return [
                'can_confirm' => false,
                'reasons' => ['Esta Hoja de trabajo ya fue confirmada.'],
            ];
        }

        $reasons = [];
        if ($file->status === OwnRevenueImportFileStatus::Analyzing || $file->analysis_token !== null) {
            $reasons[] = 'El análisis del archivo todavía está en proceso.';
        } elseif ($file->status !== OwnRevenueImportFileStatus::Ready) {
            $reasons[] = 'La Hoja de trabajo debe estar lista antes de confirmarla.';
        }

        if ($file->issues()->where('severity', OwnRevenueImportIssueSeverity::Error)->exists()) {
            $reasons[] = 'Corrige los problemas señalados antes de confirmar.';
        }

        if (! $file->rows()->where('row_kind', 'work_sheet_normalized_line')->exists()) {
            $reasons[] = 'El análisis no contiene renglones listos para confirmar.';
        }

        if ($file->analysis_revision === null) {
            $reasons[] = 'Vuelve a analizar el archivo para obtener una revisión vigente.';
        }

        $budget = $file->budget()->first();
        if ($budget === null
            || $file->budget_updated_at_at_analysis === null
            || ! $budget->updated_at->equalTo($file->budget_updated_at_at_analysis)) {
            $reasons[] = 'El presupuesto cambió después del análisis; vuelve a analizar el archivo.';
        }

        if (! $this->workSheetAbpreIsCurrent($file)) {
            $reasons[] = 'El ABPRE confirmado cambió; vuelve a analizar la Hoja de trabajo.';
        }

        if ($budget !== null && ($file->analysis_fingerprint === null
            || ! hash_equals($file->analysis_fingerprint, $this->captureSnapshot->handle($budget)->fingerprint))) {
            $reasons[] = 'Los datos de referencia cambiaron; vuelve a analizar la Hoja de trabajo.';
        }

        $hasPendingDecision = $file->issues()
            ->where('severity', OwnRevenueImportIssueSeverity::Warning)
            ->get()
            ->contains(function (OwnRevenueImportIssue $issue) use ($file): bool {
                if (($issue->context['requires_decision'] ?? false) !== true) {
                    return false;
                }

                $decision = $issue->decisions()->latest('id')->first();
                $resolvedValue = $decision?->resolved_value;

                return $decision?->resolution !== 'accepted'
                    || ($resolvedValue['accepted'] ?? false) !== true
                    || ! is_string($resolvedValue['analysis_revision'] ?? null)
                    || $file->analysis_revision === null
                    || ! hash_equals($file->analysis_revision, $resolvedValue['analysis_revision']);
            });
        if ($hasPendingDecision) {
            $reasons[] = 'Acepta las diferencias requeridas antes de confirmar.';
        }

        return [
            'can_confirm' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /** @return array<string, mixed> */
    public function emptyPreview(): array
    {
        return $this->emptyPaginator(self::PREVIEW_PER_PAGE);
    }

    /** @return array<string, mixed> */
    private function issue(OwnRevenueImportIssue $issue): array
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

    /**
     * @param  Builder<*>|Relation  $query
     * @param  list<string>  $columns
     */
    public function paginateClamped(
        Builder|Relation $query,
        int $perPage,
        string $pageName,
        array $columns = ['*'],
    ): LengthAwarePaginator {
        $paginator = $query->paginate($perPage, $columns, $pageName);

        if ($paginator->currentPage() > $paginator->lastPage()) {
            return $query->paginate(
                $perPage,
                $columns,
                $pageName,
                $paginator->lastPage(),
            );
        }

        return $paginator;
    }

    /** @return array<string, mixed> */
    private function emptyPaginator(int $perPage): array
    {
        return (new LengthAwarePaginator([], 0, $perPage, 1))->toArray();
    }

    /**
     * @param  list<string>  $itemCodes
     * @return array<string, array{abpre:string,difference:string}>
     */
    private function workSheetReconciliation(OwnRevenueImportFile $file, array $itemCodes): array
    {
        if ($itemCodes === []) {
            return [];
        }

        $abpreTotals = [];
        $abpre = $file->abpreAtAnalysis;
        foreach ($abpre?->abpreLines()
            ->whereIn('specific_item_code', $itemCodes)
            ->get(['specific_item_code', 'annual_amount_cents']) ?? [] as $line) {
            $code = $line->specific_item_code;
            $abpreTotals[$code] = $this->addUnsignedIntegers(
                $abpreTotals[$code] ?? '0',
                (string) $line->annual_amount_cents,
            );
        }

        $mismatches = $file->issues()
            ->where('code', 'work_sheet.abpre_mismatch')
            ->whereIn('field', $itemCodes)
            ->get(['field', 'context'])
            ->filter(fn (OwnRevenueImportIssue $issue): bool => ($issue->context['abpre_import_file_id'] ?? null)
                === $file->abpre_import_file_id_at_analysis)
            ->keyBy('field');
        $reconciliation = [];
        foreach ($itemCodes as $code) {
            $context = $mismatches->get($code)?->context ?? [];
            $reconciliation[$code] = [
                'abpre' => (string) ($context['abpre_total_cents'] ?? $abpreTotals[$code] ?? '0'),
                'difference' => (string) ($context['difference_cents'] ?? '0'),
            ];
        }

        return $reconciliation;
    }

    private function addUnsignedIntegers(string $left, string $right): string
    {
        $left = strrev($this->normalizeUnsignedInteger($left));
        $right = strrev($this->normalizeUnsignedInteger($right));
        $carry = 0;
        $result = '';

        for ($index = 0, $length = max(strlen($left), strlen($right)); $index < $length; $index++) {
            $total = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($total % 10);
            $carry = intdiv($total, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return $this->normalizeUnsignedInteger(strrev($result));
    }

    private function workSheetAbpreIsCurrent(OwnRevenueImportFile $file): bool
    {
        if (array_key_exists($file->id, $this->workSheetAbpreCurrent)) {
            return $this->workSheetAbpreCurrent[$file->id];
        }

        if ($file->abpre_import_file_id_at_analysis === null) {
            return $this->workSheetAbpreCurrent[$file->id] = false;
        }

        return $this->workSheetAbpreCurrent[$file->id] = OwnRevenueImportFile::query()
            ->where('own_revenue_budget_id', $file->own_revenue_budget_id)
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->latest('confirmed_at')
            ->latest('id')
            ->value('id') === $file->abpre_import_file_id_at_analysis;
    }

    private function normalizeUnsignedInteger(string $value): string
    {
        return ltrim($value, '0') ?: '0';
    }

    /**
     * @param  array<string|int, mixed>  $values
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
}
