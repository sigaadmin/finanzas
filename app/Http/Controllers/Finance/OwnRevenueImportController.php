<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueImportViewData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueImportController extends Controller
{
    private const VERSIONS_PER_PAGE = 10;

    public function __construct(private readonly OwnRevenueImportViewData $viewData) {}

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
            'budget' => $this->viewData->budget($budget),
            'session' => $session === null ? null : $this->sessionData($session),
            'slots' => collect(OwnRevenueImportFormat::cases())
                ->map(fn (OwnRevenueImportFormat $format): array => $this->slotData($budget, $format)),
            ...$this->unassignedFilesData($budget),
            'selected_file' => $selectedFile === null ? null : [
                ...$this->viewData->file($selectedFile),
                'issues' => $this->viewData->issues($selectedFile),
            ],
            'preview_file' => $previewFile === null ? null : [
                'id' => $previewFile->id,
                'name' => $previewFile->original_name,
                'version' => $previewFile->version_number,
                'status' => $previewFile->status->value,
                'analyzed_at' => $previewFile->analyzed_at?->toISOString(),
            ],
            'preview' => $previewFile === null
                ? $this->viewData->emptyPreview()
                : $this->viewData->preview($previewFile),
            'decision_warnings' => $previewFile === null
                ? $this->viewData->emptyDecisionWarnings()
                : $this->viewData->decisionWarnings($previewFile),
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
        $hasActive = (clone $history)
            ->where('status', '!=', OwnRevenueImportFileStatus::Discarded)
            ->exists();
        $versions = $this->viewData->paginateClamped(
            $this->fileSummaryQuery($budget)
                ->where('format', $format)
                ->orderByDesc('version_number')
                ->orderByDesc('id'),
            self::VERSIONS_PER_PAGE,
            "{$format->value}_versions_page",
        );

        return [
            'format' => $format->value,
            'label' => $this->formatLabel($format),
            'versions' => $versions->getCollection()
                ->map(fn (OwnRevenueImportFile $file): array => $this->viewData->file($file)),
            'versions_total' => $versions->total(),
            'versions_current_page' => $versions->currentPage(),
            'versions_last_page' => $versions->lastPage(),
            'versions_has_more' => $versions->hasMorePages(),
            'has_active' => $hasActive,
            'is_missing' => ! $hasActive,
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
        $files = $this->viewData->paginateClamped(
            $this->fileSummaryQuery($budget)
                ->whereNull('format')
                ->orderByDesc('id'),
            self::VERSIONS_PER_PAGE,
            'unassigned_page',
        );

        return [
            'unassigned_files' => $files->getCollection()
                ->map(fn (OwnRevenueImportFile $file): array => $this->viewData->file($file)),
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
    private function sessionData(OwnRevenueImportSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'created_at' => $session->created_at?->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
        ];
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
