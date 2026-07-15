<?php

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityReconciliationViewData;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueImportViewData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueActivityReconciliationController extends Controller
{
    private const GROUPS_PER_PAGE = 12;

    public function __construct(
        private readonly OwnRevenueActivityReconciliationViewData $reconciliation,
        private readonly OwnRevenueImportViewData $imports,
    ) {}

    public function __invoke(Request $request, OwnRevenueBudget $budget): Response
    {
        Gate::authorize('viewImports', $budget);

        $data = $this->reconciliation->forBudget($budget);
        $selectedFormat = $this->selectedFormat($request);
        /** @var array<string, mixed> $selectedFormatData */
        $selectedFormatData = $data['formats'][$selectedFormat->value];
        $groups = collect($selectedFormatData['groups']);
        $selectedHash = $request->string('group')->toString();
        $selectedGroup = preg_match('/^[a-f0-9]{64}$/', $selectedHash) === 1
            ? $groups->firstWhere('hash', $selectedHash)
            : null;
        $page = max(1, $request->integer('group_page', 1));
        $groupSummaries = $groups->map(fn (array $group): array => Arr::except($group, ['records']))->values();
        $paginator = new LengthAwarePaginator(
            $groupSummaries->forPage($page, self::GROUPS_PER_PAGE)->values(),
            $groupSummaries->count(),
            self::GROUPS_PER_PAGE,
            $page,
            ['path' => $request->url(), 'pageName' => 'group_page'],
        );
        $paginator->appends($request->except(['group_page', 'group']));

        return Inertia::render('finance/own-revenue/imports/reconciliation', [
            'budget' => $this->imports->budget($budget),
            'summary' => $data['summary'],
            'snapshots' => $data['snapshots'],
            'activities' => $data['activities'],
            'formats' => collect($data['formats'])
                ->map(fn (array $format): array => Arr::except($format, ['groups']))
                ->all(),
            'selected_format' => $selectedFormat->value,
            'groups' => $paginator,
            'selected_group' => $selectedGroup,
            'empty_reasons' => $data['empty_reasons'],
            'permissions' => [
                'view' => true,
                'manage' => Gate::allows('manageImports', $budget)
                    && Gate::allows('confirmImports', $budget),
            ],
        ]);
    }

    private function selectedFormat(Request $request): OwnRevenueImportFormat
    {
        $format = OwnRevenueImportFormat::tryFrom($request->string('format')->toString());

        return in_array($format, [
            OwnRevenueImportFormat::TechnicalSheet,
            OwnRevenueImportFormat::Fuel,
            OwnRevenueImportFormat::TravelExpenses,
        ], true) ? $format : OwnRevenueImportFormat::TechnicalSheet;
    }
}
