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
    $preview = file_get_contents(resource_path('js/components/finance/own-revenue/imports/abpre-preview.tsx'));
    $types = file_get_contents(resource_path('js/types/finance-own-revenue-imports.ts'));
    $controller = file_get_contents(app_path('Http/Controllers/Finance/OwnRevenueImportController.php'));

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
        ->toContain('ImportIssueList')
        ->toContain('AbprePreview')
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
        ->toContain('issues_page')
        ->toContain('import_file_id')
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

    expect($controller)
        ->toContain('year.mismatch')
        ->toContain('region.normalized')
        ->toContain('abpre.annual_mismatch')
        ->toContain('abpre.missing_justification');

    expect($slot)
        ->toContain('multiple')
        ->toContain('uploadQueue')
        ->toContain('filesToQueue')
        ->toContain('onFinish')
        ->toContain("query.delete('issues_page')")
        ->toContain("query.delete('preview_page')")
        ->toContain("query.delete('decisions_page')")
        ->toContain('file.can_reclassify')
        ->toContain('mutationFeedback.activeFileId')
        ->toContain('role="alert"')
        ->toContain('aria-live="assertive"')
        ->toContain('resolveFailedUpload')
        ->toContain('takeNextUpload')
        ->not->toContain('files[0]')
        ->and($workspace)
        ->toContain('slot.has_confirmed')
        ->toContain('slot.has_parser_pending')
        ->toContain('file.can_reclassify')
        ->toContain('slot.is_missing')
        ->toContain('mutationFeedback.activeFileId')
        ->toContain('role="alert"')
        ->toContain('aria-live="assertive"')
        ->toContain('previewFile?.analyzed_at')
        ->and($preview)
        ->toContain('importDecisionRememberKey(previewFile)')
        ->and($types)
        ->toContain('can_reclassify: boolean')
        ->toContain('has_active: boolean')
        ->toContain('is_missing: boolean')
        ->toContain('analyzed_at: string | null');
});
