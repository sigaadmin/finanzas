<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\OwnRevenue\CopyOwnRevenueBudget;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\InitializeOwnRevenueBudget;
use App\Actions\Finance\OwnRevenue\UpdateOwnRevenueBudgetSettings;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OwnRevenue\StoreOwnRevenueBudgetRequest;
use App\Http\Requests\Finance\OwnRevenue\UpdateOwnRevenueBudgetRequest;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnRevenueBudgetController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', OwnRevenueBudget::class);

        $budgets = OwnRevenueBudget::query()
            ->select([
                'id', 'fiscal_year', 'status', 'region_code', 'region_name',
                'uma_status', 'fuel_price_status', 'cog_status', 'created_at', 'updated_at',
            ])
            ->orderByDesc('fiscal_year')
            ->get()
            ->map(fn (OwnRevenueBudget $budget): array => $this->indexBudgetData($budget));

        return Inertia::render('finance/own-revenue/budgets/index', [
            'budgets' => $budgets,
            'permissions' => [
                'create' => Gate::allows('create', OwnRevenueBudget::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', OwnRevenueBudget::class);

        return Inertia::render('finance/own-revenue/budgets/create', [
            'sourceBudgets' => OwnRevenueBudget::query()
                ->select(['id', 'fiscal_year', 'status'])
                ->orderByDesc('fiscal_year')
                ->get()
                ->map(fn (OwnRevenueBudget $budget): array => [
                    'id' => $budget->id,
                    'fiscal_year' => $budget->fiscal_year,
                    'status' => $budget->status->value,
                ]),
            'permissions' => ['create' => true],
        ]);
    }

    public function store(
        StoreOwnRevenueBudgetRequest $request,
        InitializeOwnRevenueBudget $initializeBudget,
        CopyOwnRevenueBudget $copyBudget,
        StartOwnRevenueImportSession $startImportSession,
    ): RedirectResponse {
        $validated = $request->validated();
        $creationMode = $validated['creation_mode']
            ?? (isset($validated['source_budget_id']) ? 'copy' : 'blank');

        if ($creationMode === 'copy') {
            $source = OwnRevenueBudget::query()->findOrFail($validated['source_budget_id']);
            Gate::authorize('copy', $source);
            $budget = $copyBudget->handle($source, $validated['fiscal_year'], $request->user());
            $message = 'Presupuesto anual de ingresos propios copiado correctamente.';
        } elseif ($creationMode === 'import') {
            $budget = DB::transaction(function () use (
                $request,
                $validated,
                $initializeBudget,
                $startImportSession,
            ): OwnRevenueBudget {
                $budget = $initializeBudget->handle($request->user(), $validated);
                $startImportSession->handle($budget, $request->user());

                return $budget;
            });

            Inertia::flash('success', 'Presupuesto creado; cargue los archivos XLSX del ejercicio.');

            return to_route('finance.own-revenue.budgets.imports.show', $budget);
        } else {
            $budget = $initializeBudget->handle($request->user(), $validated);
            $message = 'Presupuesto anual de ingresos propios creado correctamente.';
        }

        Inertia::flash('success', $message);

        return to_route('finance.own-revenue.budgets.show', $budget);
    }

    public function show(OwnRevenueBudget $budget): Response
    {
        Gate::authorize('view', $budget);

        $budget->load([
            'activities' => fn ($query) => $query->orderBy('sort_order'),
            'signatories' => fn ($query) => $query->orderBy('sort_order'),
            'cogConfirmedBy:id,name',
        ]);

        return Inertia::render('finance/own-revenue/budgets/show', [
            'budget' => $this->showBudgetData($budget),
            'import_summary' => $this->importSummaryData($budget),
            'permissions' => [
                'updateSettings' => Gate::allows('updateSettings', $budget),
                'copy' => Gate::allows('copy', $budget),
                'confirmCog' => Gate::allows('confirmCog', $budget),
                'viewImports' => Gate::allows('viewImports', $budget),
            ],
        ]);
    }

    public function update(
        UpdateOwnRevenueBudgetRequest $request,
        OwnRevenueBudget $budget,
        UpdateOwnRevenueBudgetSettings $updateSettings,
    ): RedirectResponse {
        $updatedBudget = $updateSettings->handle($budget, $request->validated(), $request->user());
        Inertia::flash(
            'success',
            $budget->status !== $updatedBudget->status
                ? 'Fotografía institucional actualizada. Se creó una nueva versión de la propuesta para recalcular y conciliar.'
                : 'Configuración del presupuesto actualizada correctamente.',
        );

        return to_route('finance.own-revenue.budgets.show', $budget);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexBudgetData(OwnRevenueBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'fiscal_year' => $budget->fiscal_year,
            'status' => $budget->status->value,
            'region' => ['code' => $budget->region_code, 'name' => $budget->region_name],
            'uma' => ['status' => $budget->uma_status->value],
            'fuel' => ['status' => $budget->fuel_price_status->value],
            'cog' => ['status' => $budget->cog_status->value],
            'created_at' => $budget->created_at?->toISOString(),
            'updated_at' => $budget->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showBudgetData(OwnRevenueBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'fiscal_year' => $budget->fiscal_year,
            'status' => $budget->status->value,
            'settings' => $budget->only([
                'institution_name', 'responsible_unit_code', 'responsible_unit_name',
                'budget_program_code', 'budget_program_name', 'component_code', 'component_name',
                'official_activity_code', 'official_activity_name', 'region_code', 'region_name',
                'cut_percentage', 'uma_value', 'fuel_price_per_liter',
                'fuel_budget_month',
            ]) + [
                'estimated_income_cents' => $budget->estimated_income_cents === null
                    ? null
                    : (string) $budget->getRawOriginal('estimated_income_cents'),
                'uma_status' => $budget->uma_status->value,
                'fuel_price_status' => $budget->fuel_price_status->value,
            ],
            'activities' => $budget->activities->map(
                fn (OwnRevenueActivity $activity): array => $activity->only(['id', 'code', 'name', 'sort_order']),
            ),
            'signatories' => $budget->signatories->map(
                fn (OwnRevenueSignatory $signatory): array => $signatory->only([
                    'id', 'role_key', 'name', 'position', 'academic_degree', 'sort_order',
                ]),
            ),
            'cog' => [
                'row_count' => ExpenseClassification::query()
                    ->where('fiscal_year', $budget->fiscal_year)
                    ->count(),
                'source_year' => $budget->cog_source_year,
                'status' => $budget->cog_status->value,
                'confirmed_by' => $budget->cogConfirmedBy === null ? null : [
                    'id' => $budget->cogConfirmedBy->id,
                    'name' => $budget->cogConfirmedBy->name,
                ],
                'confirmed_at' => $budget->cog_confirmed_at?->toISOString(),
            ],
            'created_at' => $budget->created_at?->toISOString(),
            'updated_at' => $budget->updated_at?->toISOString(),
        ];
    }

    /** @return array{confirmed: int, missing: int, parser_pending: int} */
    private function importSummaryData(OwnRevenueBudget $budget): array
    {
        $files = $budget->importFiles()
            ->select(['format', 'status'])
            ->whereNotNull('format')
            ->where('status', '!=', OwnRevenueImportFileStatus::Discarded)
            ->get();

        return [
            'confirmed' => $files
                ->where('status', OwnRevenueImportFileStatus::Confirmed)
                ->pluck('format')
                ->unique()
                ->count(),
            'missing' => count(OwnRevenueImportFormat::cases()) - $files->pluck('format')->unique()->count(),
            'parser_pending' => $files
                ->where('status', OwnRevenueImportFileStatus::ParserPending)
                ->pluck('format')
                ->unique()
                ->count(),
        ];
    }
}
