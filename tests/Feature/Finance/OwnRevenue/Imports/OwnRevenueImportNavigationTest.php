<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function importNavigationUser(UserRole $role): User
{
    $user = User::factory()->create([
        'email' => 'import-navigation-'.fake()->uuid().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

test('manager workspace exposes five bounded slots exact money strings and mutation permissions', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create(['estimated_income_cents' => '9007199254740993']);
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
    ]);
    OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'context' => ['source_cents' => '9007199254740993'],
    ]);
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'row_kind' => 'abpre_line',
        'normalized_payload' => [
            'specificItemCode' => '21101',
            'activityCode' => 'A01',
            'sourceRegion' => 'CALKINI',
            'normalizedRegion' => 'Calkiní',
            'months' => ['january' => '9007199254740993'],
            'annualAmountCents' => '9007199254740993',
        ],
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/show', false)
            ->has('slots', 5)
            ->where('slots.0.label', 'ABPRE')
            ->where('slots.1.label', 'Hoja de trabajo')
            ->where('slots.2.label', 'Ficha técnica')
            ->where('slots.3.label', 'Combustible')
            ->where('slots.4.label', 'Viáticos')
            ->where('budget.estimated_income_cents', '9007199254740993')
            ->where('selected_file.issues.data.0.context.source_cents', '9007199254740993')
            ->where('preview.data.0.months.january', '9007199254740993')
            ->where('preview.data.0.annualAmountCents', '9007199254740993')
            ->where('permissions.upload', true)
            ->where('permissions.manage', true)
            ->where('permissions.confirm', true)
            ->where('permissions.download', true));
});

test('assistant workspace is readonly while download remains available', function () {
    $assistant = importNavigationUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($assistant)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/show', false)
            ->where('permissions.upload', false)
            ->where('permissions.manage', false)
            ->where('permissions.confirm', false)
            ->where('permissions.download', true)
            ->where('decision_warnings.has_more', false));
});

test('selected ABPRE owns both preview rows and required decision warnings', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $older = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 1,
        'status' => OwnRevenueImportFileStatus::Ready,
        'original_name' => 'ABPRE anterior.xlsx',
        'analyzed_at' => '2026-07-13 12:34:56',
    ]);
    $newer = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Ready,
        'original_name' => 'ABPRE nuevo.xlsx',
    ]);
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $older->id,
        'row_kind' => 'abpre_line',
        'normalized_payload' => ['months' => [1 => '100'], 'annualAmountCents' => '100'],
    ]);
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $newer->id,
        'row_kind' => 'abpre_line',
        'normalized_payload' => ['months' => [1 => '200'], 'annualAmountCents' => '200'],
    ]);
    $warning = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $older->id,
        'severity' => 'warning',
        'code' => 'year.mismatch',
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', [
            'budget' => $budget,
            'import_file_id' => $older->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selected_file.id', $older->id)
            ->where('preview_file.id', $older->id)
            ->where('preview_file.name', 'ABPRE anterior.xlsx')
            ->where('preview_file.version', 1)
            ->where('preview_file.analyzed_at', '2026-07-13T12:34:56.000000Z')
            ->where('preview.data.0.annualAmountCents', '100')
            ->where('decision_warnings.data.0.id', $warning->id)
            ->where('decision_warnings.total', 1));
});

test('dedicated ABPRE preview exposes safe paginated data with exact money strings', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create(['estimated_income_cents' => '9007199254740993']);
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'original_name' => 'ABPRE seguro.xlsx',
        'version_number' => 3,
    ]);

    foreach (range(1, 26) as $index) {
        OwnRevenueImportRow::factory()->create([
            'own_revenue_import_file_id' => $file->id,
            'row_kind' => 'abpre_line',
            'row_number' => $index,
            'normalized_payload' => [
                'months' => ['january' => '9007199254740993'],
                'annualAmountCents' => '9007199254740993',
            ],
        ]);

        OwnRevenueImportIssue::factory()->create([
            'own_revenue_import_file_id' => $file->id,
            'severity' => 'warning',
            'code' => 'year.mismatch',
            'context' => [
                'source_cents' => '9007199254740993',
                'exception' => '/private/storage/ABPRE.xlsx',
            ],
        ]);
    }

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $file,
            'preview_page' => 2,
            'decisions_page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/preview', false)
            ->where('budget.id', $budget->id)
            ->where('budget.estimated_income_cents', '9007199254740993')
            ->where('selected_file.id', $file->id)
            ->where('selected_file.name', 'ABPRE seguro.xlsx')
            ->where('selected_file.version', 3)
            ->where('selected_file.issue_counts.error', 0)
            ->where('selected_file.issue_counts.warning', 26)
            ->where('selected_file.issue_counts.info', 0)
            ->missing('selected_file.storage_disk')
            ->missing('selected_file.storage_path')
            ->missing('selected_file.sha256')
            ->has('preview.data', 1)
            ->where('preview.current_page', 2)
            ->where('preview.per_page', 25)
            ->where('preview.data.0.months.january', '9007199254740993')
            ->where('preview.data.0.annualAmountCents', '9007199254740993')
            ->has('decision_warnings.data', 1)
            ->where('decision_warnings.current_page', 2)
            ->where('decision_warnings.per_page', 25)
            ->where('decision_warnings.data.0.context.source_cents', '9007199254740993')
            ->missing('decision_warnings.data.0.context.exception')
            ->where('permissions.manage', true)
            ->where('permissions.confirm', true)
            ->where('permissions.download', true));
});

test('finance assistant may consult the dedicated ABPRE preview with readonly permissions', function () {
    $assistant = importNavigationUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
    ]);

    $this->actingAs($assistant)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $file,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions.upload', false)
            ->where('permissions.manage', false)
            ->where('permissions.confirm', false)
            ->where('permissions.download', true));
});

test('dedicated ABPRE preview clamps stale paginator pages', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
    ]);
    $row = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'row_kind' => 'abpre_line',
    ]);
    $warning = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'severity' => 'warning',
        'code' => 'region.normalized',
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $file,
            'preview_page' => 99,
            'decisions_page' => 99,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.current_page', 1)
            ->where('preview.data.0.id', $row->id)
            ->where('decision_warnings.current_page', 1)
            ->where('decision_warnings.data.0.id', $warning->id));
});

test('dedicated ABPRE preview enforces consultation authorization and aggregate boundaries', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $unauthorized = User::factory()->create();
    $budget = OwnRevenueBudget::factory()->create();
    $otherBudget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
    ]);
    $nonAbpre = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Fuel,
    ]);

    $previewRoute = fn (OwnRevenueBudget $routeBudget, OwnRevenueImportFile $routeFile): string => route(
        'finance.own-revenue.budgets.imports.files.preview',
        ['budget' => $routeBudget, 'importFile' => $routeFile],
    );

    $this->actingAs($unauthorized)
        ->get($previewRoute($budget, $file))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get($previewRoute($otherBudget, $file))
        ->assertNotFound();

    $this->actingAs($manager)
        ->get($previewRoute($budget, $nonAbpre))
        ->assertNotFound();
});

test('stale per-file pages clamp to the first page when selecting a shorter file', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $longFile = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 1,
    ]);
    $shortFile = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 2,
    ]);

    foreach (range(1, 55) as $index) {
        OwnRevenueImportIssue::factory()->create([
            'own_revenue_import_file_id' => $longFile->id,
            'severity' => 'warning',
            'code' => 'year.mismatch',
            'message' => "Incidencia larga {$index}",
        ]);
    }

    foreach (range(1, 30) as $index) {
        OwnRevenueImportRow::factory()->create([
            'own_revenue_import_file_id' => $longFile->id,
            'row_kind' => 'abpre_line',
            'row_number' => $index,
        ]);
    }

    $shortIssue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $shortFile->id,
        'severity' => 'warning',
        'code' => 'region.normalized',
        'message' => 'Incidencia del archivo corto',
    ]);
    $shortRow = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $shortFile->id,
        'row_kind' => 'abpre_line',
        'row_number' => 1,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', [
            'budget' => $budget,
            'import_file_id' => $shortFile->id,
            'issues_page' => 2,
            'preview_page' => 2,
            'decisions_page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selected_file.id', $shortFile->id)
            ->where('selected_file.issues.current_page', 1)
            ->where('selected_file.issues.data.0.id', $shortIssue->id)
            ->where('preview.current_page', 1)
            ->where('preview.data.0.id', $shortRow->id)
            ->where('decision_warnings.current_page', 1)
            ->where('decision_warnings.data.0.id', $shortIssue->id));
});

test('unassigned audit history exposes whether each file may be classified', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $discarded = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => null,
        'status' => OwnRevenueImportFileStatus::Discarded,
        'original_name' => 'descartado.xlsx',
    ]);
    $eligible = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => null,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'original_name' => 'clasificable.xlsx',
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('unassigned_files.0.id', $eligible->id)
            ->where('unassigned_files.0.can_reclassify', true)
            ->where('unassigned_files.1.id', $discarded->id)
            ->where('unassigned_files.1.can_reclassify', false));

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.imports.files.format.update', [
            'budget' => $budget,
            'importFile' => $eligible,
        ]), ['format' => OwnRevenueImportFormat::Fuel->value])
        ->assertRedirect();

    expect($eligible->fresh()->format)->toBe(OwnRevenueImportFormat::Fuel)
        ->and($discarded->fresh()->format)->toBeNull();
});

test('required warning decisions have their own bounded paginator', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
    ]);

    foreach (range(1, 30) as $index) {
        OwnRevenueImportIssue::factory()->create([
            'own_revenue_import_file_id' => $file->id,
            'severity' => 'warning',
            'code' => $index % 2 === 0 ? 'region.normalized' : 'abpre.annual_mismatch',
        ]);
    }

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', [
            'budget' => $budget,
            'decisions_page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('decision_warnings.data', 5)
            ->where('decision_warnings.current_page', 2)
            ->where('decision_warnings.per_page', 25)
            ->where('decision_warnings.total', 30));
});

test('slot summaries inspect complete history outside the bounded versions page', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();

    foreach (range(1, 12) as $version) {
        OwnRevenueImportFile::factory()->create([
            'own_revenue_budget_id' => $budget->id,
            'format' => OwnRevenueImportFormat::Abpre,
            'version_number' => $version,
            'status' => $version === 1
                ? OwnRevenueImportFileStatus::Confirmed
                : OwnRevenueImportFileStatus::Uploaded,
        ]);
        OwnRevenueImportFile::factory()->create([
            'own_revenue_budget_id' => $budget->id,
            'format' => OwnRevenueImportFormat::Fuel,
            'version_number' => $version,
            'status' => $version === 1
                ? OwnRevenueImportFileStatus::ParserPending
                : OwnRevenueImportFileStatus::Uploaded,
        ]);
    }

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('slots.0.versions', 10)
            ->where('slots.0.has_confirmed', true)
            ->where('slots.0.has_parser_pending', false)
            ->where('slots.0.has_active', true)
            ->where('slots.0.is_missing', false)
            ->where('slots.0.latest_status', 'uploaded')
            ->has('slots.3.versions', 10)
            ->where('slots.3.has_confirmed', false)
            ->where('slots.3.has_parser_pending', true)
            ->where('slots.3.has_active', true)
            ->where('slots.3.is_missing', false)
            ->where('slots.3.latest_status', 'uploaded'));
});

test('discarded-only history remains auditable while the format is missing', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Discarded,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('slots.0.versions_total', 1)
            ->where('slots.0.has_active', false)
            ->where('slots.0.is_missing', true));

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('import_summary.missing', 5)
            ->where('import_summary.confirmed', 0)
            ->where('import_summary.parser_pending', 0));
});

test('budget dashboard summarizes confirmed missing and parser pending import formats', function () {
    $manager = importNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
    OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Fuel,
        'status' => OwnRevenueImportFileStatus::ParserPending,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('import_summary.confirmed', 1)
            ->where('import_summary.missing', 3)
            ->where('import_summary.parser_pending', 1)
            ->where('permissions.viewImports', true));
});

test('frontend import workspace honors navigation route money and permission contracts', function () {
    $createPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/create.tsx'));
    $showPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/show.tsx'));
    $workspace = file_get_contents(resource_path('js/pages/finance/own-revenue/imports/show.tsx'));
    $slot = file_get_contents(resource_path('js/components/finance/own-revenue/imports/import-file-slot.tsx'));
    $issues = file_get_contents(resource_path('js/components/finance/own-revenue/imports/import-issue-list.tsx'));
    $frontendState = file_get_contents(resource_path('js/components/finance/own-revenue/imports/import-workspace-state.js'));
    $preview = file_get_contents(resource_path('js/components/finance/own-revenue/imports/abpre-preview.tsx'));
    $types = file_get_contents(resource_path('js/types/finance-own-revenue-imports.ts'));
    $controller = file_get_contents(app_path('Http/Controllers/Finance/OwnRevenueImportController.php'));
    $viewData = file_get_contents(app_path('Services/Finance/OwnRevenue/Imports/OwnRevenueImportViewData.php'));

    expect($createPage)
        ->toContain("type Mode = 'blank' | 'copy' | 'import'")
        ->toContain('Desde archivos XLSX')
        ->toContain("creation_mode: 'import'")
        ->toContain('form.submit(store())')
        ->and($showPage)
        ->toContain('Importaciones XLSX')
        ->toContain('@/routes/finance/own-revenue/budgets/imports')
        ->toContain('imports.show(budget.id)')
        ->and($workspace)
        ->toContain('ImportFileSlot')
        ->not->toContain('<ImportIssueList')
        ->not->toContain('<AbprePreview')
        ->not->toContain('Parser pendiente')
        ->toContain('Importar archivos del presupuesto')
        ->toContain('Revisión no disponible')
        ->toContain('Más información')
        ->toContain('@/actions/App/Http/Controllers/Finance')
        ->not->toContain("fetch('")
        ->not->toContain('FileReader')
        ->and($slot)
        ->toContain('onDrop')
        ->toContain('progress.percentage')
        ->toContain('force_reanalysis')
        ->toContain('permissions.upload')
        ->toContain('permissions.manage')
        ->toContain('versions_current_page')
        ->and($issues)
        ->toContain('DialogContent')
        ->toContain('DialogTitle')
        ->toContain('DialogDescription')
        ->toContain('Ver incidencias')
        ->toContain('No se encontraron problemas')
        ->toContain('preserveState')
        ->toContain('importIssueDialogOpenAction')
        ->toContain('importIssueDialogState')
        ->toContain('importIssuePageQuery')
        ->and($preview)
        ->toContain('preview_page')
        ->toContain('formatCents')
        ->toContain('useRemember')
        ->toContain('decisionWarnings')
        ->toContain('decisions_page')
        ->toContain('resolvedDecisionCount')
        ->toContain('decisionWarnings.total')
        ->toContain('<option value="manual">')
        ->toContain('<option value="xlsx">')
        ->toContain('<option value="custom">')
        ->not->toContain("resolution: 'manual' as const")
        ->not->toContain('useEffect')
        ->not->toContain('parseFloat(')
        ->not->toContain('Number(')
        ->and($types)
        ->toContain('export type OwnRevenueImportFormat =')
        ->toContain('estimated_income_cents: string | null')
        ->toContain('months: Record<string, string>')
        ->toContain('permissions: OwnRevenueImportPermissions');

    expect($controller.$viewData)
        ->toContain('year.mismatch')
        ->toContain('region.normalized')
        ->toContain('abpre.annual_mismatch')
        ->toContain('abpre.missing_justification');

    expect($slot)
        ->toContain('multiple')
        ->toContain('uploadQueue')
        ->toContain('filesToQueue')
        ->toContain('onFinish')
        ->toContain('selectImportFileQuery')
        ->toContain('file.can_reclassify')
        ->toContain('mutationFeedback.activeFileId')
        ->toContain('role="alert"')
        ->toContain('aria-live="assertive"')
        ->toContain('resolveFailedUpload')
        ->toContain('takeNextUpload')
        ->toContain('importFilePresentation')
        ->toContain('ImportIssueList')
        ->toContain('Ver vista previa')
        ->toContain('@/routes/finance/own-revenue/budgets/imports/files')
        ->not->toContain('Parser pendiente')
        ->not->toContain('files[0]')
        ->and($frontendState)
        ->toContain('export function selectImportFileQuery')
        ->toContain('export function importIssuePageQuery')
        ->toContain('export function importIssueDialogState')
        ->toContain('export function importIssueDialogOpenAction')
        ->and($workspace)
        ->toContain('slot.has_confirmed')
        ->toContain('slot.has_parser_pending')
        ->toContain('file.can_reclassify')
        ->toContain('slot.is_missing')
        ->toContain('mutationFeedback.activeFileId')
        ->toContain('role="alert"')
        ->toContain('aria-live="assertive"')
        ->not->toContain('previewFile?.analyzed_at')
        ->and($preview)
        ->toContain('importDecisionRememberKey(previewFile)')
        ->and($types)
        ->toContain('can_reclassify: boolean')
        ->toContain('has_active: boolean')
        ->toContain('is_missing: boolean')
        ->toContain('analyzed_at: string | null');
});
