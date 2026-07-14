<?php

use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function importManagementUser(UserRole $role = UserRole::FinanceManager): User
{
    $user = User::factory()->create([
        'email' => 'import-http-'.fake()->uuid().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

/** @return array<string, mixed> */
function importBudgetData(array $overrides = []): array
{
    return array_replace([
        'creation_mode' => 'import',
        'fiscal_year' => 2031,
        'institution_name' => 'Centro Regional de Educación Normal',
        'responsible_unit_code' => '2112102003',
        'responsible_unit_name' => 'Dirección del Plantel',
        'budget_program_code' => 'E062',
        'budget_program_name' => 'Formación Inicial Docente',
        'component_code' => 'C01',
        'component_name' => 'Servicios de formación docente',
        'official_activity_code' => 'A01',
        'official_activity_name' => 'Operación de los programas de formación docente',
    ], $overrides);
}

test('import routes expose the required names methods and paths', function () {
    expect(route('finance.own-revenue.budgets.imports.show', 10, absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports')
        ->and(route('finance.own-revenue.budgets.imports.files.store', 10, absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files')
        ->and(route('finance.own-revenue.budgets.imports.files.format.update', [10, 20], absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files/20/format')
        ->and(route('finance.own-revenue.budgets.imports.files.analyze', [10, 20], absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files/20/analyze')
        ->and(route('finance.own-revenue.budgets.imports.files.abpre.confirm', [10, 20], absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files/20/abpre/confirm')
        ->and(route('finance.own-revenue.budgets.imports.files.download', [10, 20], absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files/20/download')
        ->and(route('finance.own-revenue.budgets.imports.files.discard', [10, 20], absolute: false))
        ->toBe('/finance/own-revenue/budgets/10/imports/files/20')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.show')?->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.store')?->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.format.update')?->methods())->toBe(['PUT'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.analyze')?->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.abpre.confirm')?->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.download')?->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.imports.files.discard')?->methods())->toBe(['DELETE']);
});

test('import creation atomically creates a budget and open session with pending annual values', function () {
    $manager = importManagementUser();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), importBudgetData([
            'estimated_income_cents' => null,
            'cut_percentage' => null,
            'uma_value' => null,
            'fuel_price_per_liter' => null,
        ]))
        ->assertRedirect(route('finance.own-revenue.budgets.imports.show', 1));

    $budget = OwnRevenueBudget::query()->sole();
    $session = OwnRevenueImportSession::query()->sole();

    expect($session->own_revenue_budget_id)->toBe($budget->id)
        ->and($session->created_by)->toBe($manager->id)
        ->and($session->status->value)->toBe('open')
        ->and($budget->estimated_income_cents)->toBeNull()
        ->and($budget->uma_value)->toBeNull()
        ->and($budget->fuel_price_per_liter)->toBeNull();
});

test('import creation rolls back the budget when opening its session fails', function () {
    $manager = importManagementUser();
    $startSession = Mockery::mock(StartOwnRevenueImportSession::class);
    $startSession->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Session failure'));
    $this->app->instance(StartOwnRevenueImportSession::class, $startSession);
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), importBudgetData()))
        ->toThrow(RuntimeException::class, 'Session failure');

    expect(OwnRevenueBudget::query()->count())->toBe(0)
        ->and(OwnRevenueImportSession::query()->count())->toBe(0);
});

test('import creation prohibits a source budget and validates its mode', function () {
    $manager = importManagementUser();
    $source = OwnRevenueBudget::factory()->create(['fiscal_year' => 2030]);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), importBudgetData([
            'source_budget_id' => $source->id,
        ]))
        ->assertSessionHasErrors('source_budget_id');

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), importBudgetData([
            'creation_mode' => 'spreadsheet',
        ]))
        ->assertSessionHasErrors('creation_mode');

    expect(OwnRevenueBudget::query()->where('fiscal_year', 2031)->exists())->toBeFalse();
});

test('upload request validates xlsx files size and force reanalysis', function () {
    Storage::fake('local');
    $manager = importManagementUser();
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.store', $budget), [])
        ->assertSessionHasErrors('file');

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.store', $budget), [
            'file' => UploadedFile::fake()->create('datos.csv', 10, 'text/csv'),
            'force_reanalysis' => 'definitely',
        ])
        ->assertSessionHasErrors(['file', 'force_reanalysis']);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.store', $budget), [
            'file' => UploadedFile::fake()->create(
                'oversize.xlsx',
                (20 * 1024) + 1,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ])
        ->assertSessionHasErrors('file');
});

test('format and confirmation requests enforce strict enum and nested decision validation', function () {
    $manager = importManagementUser();
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create(['own_revenue_budget_id' => $budget->id]);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.imports.files.format.update', [$budget, $file]), [
            'format' => 'pdf',
        ])
        ->assertSessionHasErrors('format');

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.abpre.confirm', [$budget, $file]), [
            'decisions' => [[
                'issue_id' => 'not-an-integer',
                'resolution' => 'ignored',
            ]],
        ])
        ->assertSessionHasErrors([
            'decisions.0.issue_id',
            'decisions.0.resolution',
            'decisions.0.resolved_value',
        ]);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.abpre.confirm', [$budget, $file]), [
            'decisions' => [],
        ])
        ->assertSessionDoesntHaveErrors('decisions');
});

test('consultation roles may view and download but all mutations are forbidden', function (UserRole $role) {
    Storage::fake('local');
    $user = importManagementUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create(['own_revenue_budget_id' => $budget->id]);
    Storage::disk('local')->put($file->storage_path, 'private workbook bytes');

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk();
    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.imports.files.download', [$budget, $file]))
        ->assertOk()
        ->assertDownload($file->original_name);
    $this->actingAs($user)
        ->post(route('finance.own-revenue.budgets.imports.files.store', $budget), [])
        ->assertForbidden();
    $this->actingAs($user)
        ->put(route('finance.own-revenue.budgets.imports.files.format.update', [$budget, $file]), [
            'format' => OwnRevenueImportFormat::Fuel->value,
        ])
        ->assertForbidden();
    $this->actingAs($user)
        ->post(route('finance.own-revenue.budgets.imports.files.analyze', [$budget, $file]))
        ->assertForbidden();
    $this->actingAs($user)
        ->post(route('finance.own-revenue.budgets.imports.files.abpre.confirm', [$budget, $file]), [
            'decisions' => [],
        ])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('finance.own-revenue.budgets.imports.files.discard', [$budget, $file]))
        ->assertForbidden();
})->with([UserRole::FinanceAssistant, UserRole::FinanceAuditor]);

test('nested file endpoints return not found when the file belongs to another budget', function () {
    Storage::fake('local');
    $manager = importManagementUser();
    $budget = OwnRevenueBudget::factory()->create();
    $otherBudget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create(['own_revenue_budget_id' => $otherBudget->id]);
    Storage::disk('local')->put($file->storage_path, 'private workbook bytes');

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.download', [$budget, $file]))
        ->assertNotFound();
    $this->actingAs($manager)
        ->delete(route('finance.own-revenue.budgets.imports.files.discard', [$budget, $file]))
        ->assertNotFound();
    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.imports.files.format.update', [$budget, $file]), [
            'format' => OwnRevenueImportFormat::Fuel->value,
        ])
        ->assertNotFound();
    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.analyze', [$budget, $file]))
        ->assertNotFound();
    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.abpre.confirm', [$budget, $file]), [
            'decisions' => [],
        ])
        ->assertNotFound();
});

test('manager downloads privately and may discard only unconfirmed files', function () {
    Storage::fake('local');
    $manager = importManagementUser();
    $budget = OwnRevenueBudget::factory()->create();
    $uploaded = OwnRevenueImportFile::factory()->create(['own_revenue_budget_id' => $budget->id]);
    $confirmed = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Confirmed,
    ]);
    $replacedConfirmed = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'version_number' => 3,
        'status' => OwnRevenueImportFileStatus::Replaced,
        'confirmed_at' => now(),
    ]);
    Storage::disk('local')->put($uploaded->storage_path, 'private workbook bytes');

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.download', [$budget, $uploaded]))
        ->assertOk()
        ->assertDownload($uploaded->original_name);

    $this->actingAs($manager)
        ->delete(route('finance.own-revenue.budgets.imports.files.discard', [$budget, $uploaded]))
        ->assertRedirect(route('finance.own-revenue.budgets.imports.show', $budget));

    $this->actingAs($manager)
        ->from(route('finance.own-revenue.budgets.imports.show', $budget))
        ->delete(route('finance.own-revenue.budgets.imports.files.discard', [$budget, $confirmed]))
        ->assertRedirect(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertSessionHasErrors('file');

    $this->actingAs($manager)
        ->from(route('finance.own-revenue.budgets.imports.show', $budget))
        ->delete(route('finance.own-revenue.budgets.imports.files.discard', [$budget, $replacedConfirmed]))
        ->assertRedirect(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertSessionHasErrors('file');

    expect($uploaded->fresh()->status)->toBe(OwnRevenueImportFileStatus::Discarded)
        ->and($confirmed->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($replacedConfirmed->fresh()->status)->toBe(OwnRevenueImportFileStatus::Replaced);
});

test('workspace returns five ordered slots safe file summaries issue counts and exact string preview amounts', function () {
    $manager = importManagementUser();
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2031,
        'estimated_income_cents' => '9007199254740993',
    ]);
    $session = OwnRevenueImportSession::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'created_by' => $manager->id,
    ]);
    $older = OwnRevenueImportFile::factory()->create([
        'own_revenue_import_session_id' => $session->id,
        'own_revenue_budget_id' => $budget->id,
        'version_number' => 1,
        'status' => OwnRevenueImportFileStatus::Replaced,
    ]);
    $current = OwnRevenueImportFile::factory()->create([
        'own_revenue_import_session_id' => $session->id,
        'own_revenue_budget_id' => $budget->id,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Ready,
        'detection_confidence' => 97,
        'analyzed_at' => now(),
    ]);
    $errorIssue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $current->id,
        'severity' => OwnRevenueImportIssueSeverity::Error,
        'context' => [
            'exception' => '/private/storage/path/workbook.xlsx',
            'detected_year' => 2030,
        ],
    ]);
    OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $current->id,
        'severity' => OwnRevenueImportIssueSeverity::Warning,
    ]);
    OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $current->id,
        'severity' => OwnRevenueImportIssueSeverity::Info,
    ]);
    OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $current->id,
        'sheet_name' => '__normalized_abpre__',
        'row_number' => 1,
        'row_kind' => 'abpre_line',
        'normalized_payload' => [
            'specificItemCode' => '21101',
            'annualAmountCents' => '9007199254740993',
            'months' => [1 => '9007199254740993', 2 => '0000000000000001', 3 => 123],
        ],
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/show', false)
            ->where('budget.id', $budget->id)
            ->where('budget.fiscal_year', 2031)
            ->where('budget.estimated_income_cents', '9007199254740993')
            ->where('session.id', $session->id)
            ->where('session.status', 'open')
            ->has('slots', 5)
            ->where('slots.0.format', 'abpre')
            ->where('slots.1.format', 'work_sheet')
            ->where('slots.2.format', 'technical_sheet')
            ->where('slots.3.format', 'fuel')
            ->where('slots.4.format', 'travel_expenses')
            ->has('slots.0.versions', 2)
            ->where('slots.0.versions.0.id', $current->id)
            ->where('slots.0.versions.0.name', $current->original_name)
            ->where('slots.0.versions.0.size', $current->size_bytes)
            ->where('slots.0.versions.0.format', 'abpre')
            ->where('slots.0.versions.0.detected_format', 'abpre')
            ->where('slots.0.versions.0.year', 2027)
            ->where('slots.0.versions.0.version', 2)
            ->where('slots.0.versions.0.status', 'ready')
            ->where('slots.0.versions.0.confidence', 97)
            ->where('slots.0.versions.0.issue_counts.error', 1)
            ->where('slots.0.versions.0.issue_counts.warning', 1)
            ->where('slots.0.versions.0.issue_counts.info', 1)
            ->where('slots.0.versions.0.issues.0.id', $errorIssue->id)
            ->where('slots.0.versions.0.issues.0.context.detected_year', 2030)
            ->missing('slots.0.versions.0.issues.0.context.exception')
            ->where('preview.data.0.annualAmountCents', '9007199254740993')
            ->where('preview.data.0.months.1', '9007199254740993')
            ->where('preview.data.0.months.2', '0000000000000001')
            ->where('preview.data.0.months.3', '123')
            ->where('permissions.upload', true)
            ->where('permissions.manage', true)
            ->where('permissions.confirm', true)
            ->where('permissions.download', true)
            ->missing('slots.0.versions.0.storage_disk')
            ->missing('slots.0.versions.0.storage_path')
            ->missing('slots.0.versions.0.sha256'));

    expect($older->id)->not->toBe($current->id);
});

test('consultation workspace exposes read only permissions', function () {
    $assistant = importManagementUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($assistant)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions.upload', false)
            ->where('permissions.manage', false)
            ->where('permissions.confirm', false)
            ->where('permissions.download', true));
});
