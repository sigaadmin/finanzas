<?php

use App\Actions\Finance\OwnRevenue\Imports\CaptureOwnRevenueImportAnalysisSnapshot;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportDecision;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function workSheetPreviewUser(UserRole $role): User
{
    $user = User::factory()->create([
        'email' => 'work-sheet-preview-'.fake()->uuid().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

/** @return array{OwnRevenueBudget, OwnRevenueImportFile, OwnRevenueImportFile} */
function workSheetPreviewScenario(): array
{
    $budget = OwnRevenueBudget::factory()->create();
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales',
        'concept_code' => '2100',
        'concept_name' => 'Administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Insumos',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Papelería',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $abpre = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now()->subMinute(),
    ]);
    OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => '9007199254740993',
    ]);
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'detected_format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Ready,
        'analysis_revision' => (string) Str::uuid(),
        'abpre_import_file_id_at_analysis' => $abpre->id,
        'analyzed_at' => now(),
        'original_name' => 'Hoja de trabajo julio.xlsx',
    ]);
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'sheet_name' => '__normalized_work_sheet__',
        'row_number' => 1,
        'row_kind' => 'work_sheet_normalized_line',
        'normalized_payload' => [
            'activityCode' => 'A03-A01',
            'activityName' => 'Administración de recursos',
            'itemName' => 'Papelería para oficina',
            'specificItemCode' => '21101',
            'regionCode' => '02-001',
            'regionName' => 'Felipe Carrillo Puerto',
            'sourceRegions' => [
                ['code' => '02-001', 'name' => 'Felipe Carrillo Puerto'],
                ['code' => '02-001', 'name' => 'Felipe Carrillo Puerto'],
                ['code' => '03-002', 'name' => 'Región auxiliar'],
            ],
            'months' => ['1' => '9007199254740994', '2' => '1'],
            'annualAmountCents' => '9007199254740995',
            'sourceRows' => [5, 6],
        ],
    ]);
    $issue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'severity' => OwnRevenueImportIssueSeverity::Warning,
        'code' => 'work_sheet.abpre_mismatch',
        'field' => '21101',
        'message' => 'La partida 21101 difiere del importe confirmado en el ABPRE.',
        'context' => [
            'specific_item_code' => '21101',
            'work_sheet_total_cents' => '9007199254740995',
            'abpre_total_cents' => '9007199254740993',
            'difference_cents' => '2',
            'abpre_import_file_id' => $abpre->id,
            'requires_decision' => true,
            'exception' => '/private/tmp/technical.xlsx',
        ],
    ]);
    OwnRevenueImportDecision::factory()->create([
        'own_revenue_import_issue_id' => $issue->id,
        'resolution' => 'accepted',
        'resolved_value' => [
            'accepted' => true,
            'analysis_revision' => $file->analysis_revision,
        ],
        'justification' => 'Se conserva la calendarización operativa.',
    ]);
    OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'severity' => OwnRevenueImportIssueSeverity::Error,
        'code' => 'work_sheet.invalid_activity',
        'field' => 'activity_code',
        'message' => 'Corrija la actividad antes de continuar.',
        'context' => ['token' => 'secret', 'source_payload' => ['raw' => true]],
    ]);

    return [$budget, $file, $abpre];
}

test('work sheet preview exposes operational exact and reconciled props', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    [$budget, $file] = workSheetPreviewScenario();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $file,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/preview', false)
            ->where('selected_file.id', $file->id)
            ->where('selected_file.format', 'work_sheet')
            ->where('selected_file.analysis_revision', $file->analysis_revision)
            ->where('view_state', 'ready')
            ->where('decisions_enabled', true)
            ->where('can_confirm', false)
            ->where('confirm_reasons.0', 'Corrige los problemas señalados antes de confirmar.')
            ->has('preview.data', 1)
            ->where('preview.data.0.activityCode', 'A03-A01')
            ->where('preview.data.0.itemName', 'Papelería para oficina')
            ->where('preview.data.0.specificItemCode', '21101')
            ->where('preview.data.0.sourceRegions', [
                ['code' => '02-001', 'name' => 'Felipe Carrillo Puerto'],
                ['code' => '03-002', 'name' => 'Región auxiliar'],
            ])
            ->where('preview.data.0.months.1', '9007199254740994')
            ->where('preview.data.0.annualAmountCents', '9007199254740995')
            ->where('preview.data.0.abpreAmountCents', '9007199254740993')
            ->where('preview.data.0.differenceCents', '2')
            ->has('blocking_issues.data', 1)
            ->where('blocking_issues.data.0.message', 'Corrija la actividad antes de continuar.')
            ->missing('blocking_issues.data.0.code')
            ->missing('blocking_issues.data.0.field')
            ->missing('blocking_issues.data.0.context')
            ->has('review_issues.data', 1)
            ->where('review_issues.data.0.item_code', '21101')
            ->where('review_issues.data.0.work_sheet_amount_cents', '9007199254740995')
            ->where('review_issues.data.0.abpre_amount_cents', '9007199254740993')
            ->where('review_issues.data.0.difference_cents', '2')
            ->where('review_issues.data.0.requires_decision', true)
            ->where('review_issues.data.0.decision.status', 'accepted')
            ->where('review_issues.data.0.decision.justification', 'Se conserva la calendarización operativa.')
            ->missing('review_issues.data.0.code')
            ->missing('review_issues.data.0.context')
            ->where('permissions.manage', true));
});

test('work sheet preview exposes server confirmation eligibility and human reasons', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    [$budget, $file] = workSheetPreviewScenario();
    $file->issues()->where('severity', OwnRevenueImportIssueSeverity::Error)->delete();
    $file->forceFill([
        'budget_updated_at_at_analysis' => $budget->updated_at,
    ])->save();
    $file->forceFill([
        'analysis_fingerprint' => app(CaptureOwnRevenueImportAnalysisSnapshot::class)->handle($budget->fresh())->fingerprint,
    ])->save();

    $route = route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]);

    $this->actingAs($manager)->get($route)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can_confirm', true)
            ->where('confirm_reasons', []));

    $file->update([
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($manager)->get($route)
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('view_state', 'confirmed')
            ->where('can_confirm', false)
            ->where('confirm_reasons.0', 'Esta Hoja de trabajo ya fue confirmada.'));
});

test('consultation roles see work sheet preview without mutation permission', function (UserRole $role) {
    $viewer = workSheetPreviewUser($role);
    [$budget, $file] = workSheetPreviewScenario();

    $this->actingAs($viewer)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $budget,
            'importFile' => $file,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions.manage', false)
            ->where('can_confirm', false)
            ->where('decisions_enabled', false)
            ->where('permissions.confirm', false)
            ->where('permissions.download', true)
            ->has('review_issues.data', 1)
            ->missing('selected_file.storage_path')
            ->missing('selected_file.analysis_token'));
})->with([UserRole::FinanceAssistant, UserRole::FinanceAuditor]);

test('work sheet preview describes files that have not been analyzed or whose analysis failed', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'detected_format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'analyzed_at' => null,
    ]);
    $route = fn (): string => route('finance.own-revenue.budgets.imports.files.preview', [
        'budget' => $budget,
        'importFile' => $file,
    ]);

    $this->actingAs($manager)->get($route())
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('view_state', 'not_analyzed')
            ->has('preview.data', 0));

    $file->update(['status' => OwnRevenueImportFileStatus::Failed]);

    $this->actingAs($manager)->get($route())
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('view_state', 'failed')
            ->has('preview.data', 0));
});

test('an active first analysis is described as in progress', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Analyzing,
        'analysis_token' => (string) Str::uuid(),
        'analyzed_at' => null,
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('view_state', 'analyzing')
            ->where('decisions_enabled', false));
});

test('preview keeps the analyzed ABPRE snapshot and invalidates stale decisions after replacement', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    [$budget, $file, $snapshotAbpre] = workSheetPreviewScenario();
    $snapshotAbpre->update(['status' => OwnRevenueImportFileStatus::Replaced]);
    $currentAbpre = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now(),
        'version_number' => 2,
    ]);
    $classification = ExpenseClassification::query()->where('fiscal_year', $budget->fiscal_year)->firstOrFail();
    OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $currentAbpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => '1',
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('view_state', 'abpre_changed')
            ->where('decisions_enabled', false)
            ->where('preview.data.0.abpreAmountCents', '9007199254740993')
            ->where('preview.data.0.differenceCents', '2')
            ->where('review_issues.data.0.decision', null));
});

test('multiple activities in one item show the same ABPRE item totals', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    [$budget, $file] = workSheetPreviewScenario();
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'sheet_name' => '__normalized_work_sheet__',
        'row_number' => 2,
        'row_kind' => 'work_sheet_normalized_line',
        'normalized_payload' => [
            'activityCode' => 'A03-A02',
            'activityName' => 'Segunda actividad',
            'itemName' => 'Papelería',
            'specificItemCode' => '21101',
            'sourceRegions' => [],
            'months' => ['1' => '1'],
            'annualAmountCents' => '1',
        ],
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('preview.data', 2)
            ->where('preview.data.0.abpreAmountCents', '9007199254740993')
            ->where('preview.data.1.abpreAmountCents', '9007199254740993')
            ->where('preview.data.0.differenceCents', '2')
            ->where('preview.data.1.differenceCents', '2'));
});

test('preview paginates before resolving only the visible item codes', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    [$budget, $file] = workSheetPreviewScenario();

    foreach (range(2, 51) as $rowNumber) {
        OwnRevenueImportRow::factory()->create([
            'own_revenue_import_file_id' => $file->id,
            'sheet_name' => '__normalized_work_sheet__',
            'row_number' => $rowNumber,
            'row_kind' => 'work_sheet_normalized_line',
            'normalized_payload' => [
                'activityCode' => 'A03-'.str_pad((string) $rowNumber, 2, '0', STR_PAD_LEFT),
                'activityName' => 'Actividad '.$rowNumber,
                'itemName' => 'Insumo '.$rowNumber,
                'specificItemCode' => (string) (22000 + $rowNumber),
                'sourceRegions' => [],
                'months' => ['1' => '1'],
                'annualAmountCents' => '1',
            ],
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [
            $budget,
            $file,
            'preview_page' => 2,
        ]));
    $normalizedPayloadQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'normalized_payload'));

    $response->assertInertia(fn (Assert $page): Assert => $page
        ->where('preview.current_page', 2)
        ->has('preview.data', 25));
    expect($normalizedPayloadQueries)->not->toBeEmpty()
        ->and($normalizedPayloadQueries->every(
            fn (string $query): bool => str_contains(strtolower($query), 'limit'),
        ))->toBeTrue();
});

test('work sheet preview enforces authorization budget boundaries and supported formats', function () {
    $manager = workSheetPreviewUser(UserRole::FinanceManager);
    $publicUser = workSheetPreviewUser(UserRole::Public);
    [$budget, $file] = workSheetPreviewScenario();
    $otherBudget = OwnRevenueBudget::factory()->create();
    $unsupported = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Fuel,
    ]);

    $this->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]))
        ->assertRedirect();
    $this->actingAs($publicUser)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file]))
        ->assertForbidden();
    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$otherBudget, $file]))
        ->assertNotFound();
    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$budget, $unsupported]))
        ->assertNotFound();
});
