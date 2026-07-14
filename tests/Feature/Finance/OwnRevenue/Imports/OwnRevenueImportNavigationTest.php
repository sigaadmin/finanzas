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
            ->where('preview.data.0.annualAmountCents', '100')
            ->where('decision_warnings.data.0.id', $warning->id)
            ->where('decision_warnings.total', 1));
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
            ->where('slots.0.latest_status', 'uploaded')
            ->has('slots.3.versions', 10)
            ->where('slots.3.has_confirmed', false)
            ->where('slots.3.has_parser_pending', true)
            ->where('slots.3.latest_status', 'uploaded'));
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
        ->not->toContain('files[0]')
        ->and($workspace)
        ->toContain('slot.has_confirmed')
        ->toContain('slot.has_parser_pending');
});
