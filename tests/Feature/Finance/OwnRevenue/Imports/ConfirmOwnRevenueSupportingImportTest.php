<?php

use App\Actions\Finance\OwnRevenue\Imports\CaptureOwnRevenueImportAnalysisSnapshot;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function supportingConfirmationUser(): User
{
    $email = 'supporting-confirmation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => UserRole::FinanceManager, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{file: OwnRevenueImportFile, source_row_id: int, payload: array<string, mixed>} */
function readySupportingFile(User $manager, OwnRevenueImportFormat $format): array
{
    $budget = OwnRevenueBudget::factory()->create();
    ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales, útiles y equipos menores de oficina',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $contents = 'supporting-'.$format->value;
    $revision = (string) Str::uuid();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => $format,
        'detected_format' => $format,
        'detected_year' => $budget->fiscal_year,
        'status' => OwnRevenueImportFileStatus::Ready,
        'analysis_revision' => $revision,
        'budget_updated_at_at_analysis' => $budget->updated_at,
        'analyzed_at' => now(),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $file->forceFill(['sha256' => hash('sha256', $contents)])->save();

    $sourcePayload = ['description' => 'Dato original'];
    $source = $file->rows()->create([
        'sheet_name' => 'Formato',
        'row_number' => 9,
        'row_kind' => $format->value.'_line',
        'row_hash' => app(CanonicalJson::class)->hash($sourcePayload),
        'source_payload' => $sourcePayload,
        'normalized_payload' => null,
    ]);
    $payload = match ($format) {
        OwnRevenueImportFormat::TechnicalSheet => [
            'specificItemCode' => '21101', 'quantity' => '2', 'unit' => 'Pieza',
            'description' => 'Material', 'regionCode' => '02-001', 'regionName' => 'Felipe Carrillo Puerto',
            'amountCents' => '12500', 'budgetMonth' => 4,
        ],
        OwnRevenueImportFormat::Fuel => [
            'month' => 4, 'reason' => 'Comisión', 'vehicleModel' => 'Unidad 1',
            'outboundOrigin' => 'Plantel', 'outboundDestination' => 'Cancún',
            'outboundKilometers' => '220', 'returnOrigin' => 'Cancún', 'returnDestination' => 'Plantel',
            'returnKilometers' => '220', 'liters' => '44', 'fuelPrice' => '24.50', 'amountCents' => '107800',
        ],
        OwnRevenueImportFormat::TravelExpenses => [
            'month' => 4, 'reason' => 'Comisión', 'personName' => 'Persona', 'position' => 'Docente',
            'commissionDays' => '2', 'destination' => 'Cancún', 'perDiemUma' => '10', 'lodgingUma' => '8',
            'umaValue' => '117.31', 'perDiemAmountCents' => '117310', 'lodgingAmountCents' => '93848',
            'totalAmountCents' => '211158', 'flightAmountCents' => '0',
        ],
        default => throw new LogicException('Formato no compatible.'),
    };
    $normalized = $file->rows()->create([
        'sheet_name' => '__normalized__',
        'row_number' => 1,
        'row_kind' => $format->value.'_normalized_line',
        'row_hash' => app(CanonicalJson::class)->hash($payload),
        'source_payload' => ['source_rows' => [9]],
        'normalized_payload' => $payload,
    ]);
    $file->forceFill([
        'analysis_fingerprint' => app(CaptureOwnRevenueImportAnalysisSnapshot::class)->handle($budget->fresh())->fingerprint,
    ])->save();

    return ['file' => $file->fresh(), 'source_row_id' => $source->id, 'payload' => $payload];
}

function refreshSupportingConfirmationSnapshot(OwnRevenueImportFile $file): OwnRevenueImportFile
{
    $budget = $file->budget->fresh();
    $file->forceFill([
        'budget_updated_at_at_analysis' => $budget->updated_at,
        'analysis_fingerprint' => app(CaptureOwnRevenueImportAnalysisSnapshot::class)->handle($budget)->fingerprint,
    ])->save();

    return $file->fresh();
}

test('a supporting file without an analysis fingerprint cannot be confirmed', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::Fuel);
    $file->forceFill(['analysis_fingerprint' => null])->save();

    $this->actingAs($manager)
        ->from(route('finance.own-revenue.budgets.imports.files.preview', [$file->budget, $file]))
        ->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [$file->budget, $file]), [
            'analysis_revision' => $file->analysis_revision,
        ])
        ->assertSessionHasErrors('file');

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready);
});

test('a supporting file with a stale analysis fingerprint cannot be confirmed', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::Fuel);
    $file->forceFill(['analysis_fingerprint' => str_repeat('0', 64)])->save();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [$file->budget, $file]), [
            'analysis_revision' => $file->analysis_revision,
        ])
        ->assertSessionHasErrors('file');

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready);
});

test('a detected fiscal year mismatch must be present in the current analysis', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::TravelExpenses);
    $file->forceFill(['detected_year' => $file->budget->fiscal_year - 1])->save();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [$file->budget, $file]), [
            'analysis_revision' => $file->analysis_revision,
        ])
        ->assertSessionHasErrors('file');

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready);
});

test('a supporting warning requires a current accepted decision', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::Fuel);
    $issue = $file->issues()->create([
        'severity' => 'warning',
        'code' => 'year.mismatch',
        'field' => 'fiscal_year',
        'message' => 'El ejercicio detectado no coincide.',
        'context' => [
            'detected_year' => $file->budget->fiscal_year - 1,
            'fiscal_year' => $file->budget->fiscal_year,
            'requires_decision' => true,
        ],
    ]);

    $confirmRoute = route('finance.own-revenue.budgets.imports.files.supporting.confirm', [$file->budget, $file]);
    $this->withoutVite();
    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$file->budget, $file]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can_confirm', false)
            ->where('decision_warnings.data.0.id', $issue->id)
            ->where('decision_warnings.data.0.decision', null));

    $this->actingAs($manager)->post($confirmRoute, [
        'analysis_revision' => $file->analysis_revision,
    ])->assertSessionHasErrors('file');

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.issues.decision.store', [
        $file->budget, $file, $issue,
    ]), [
        'analysis_revision' => $file->analysis_revision,
        'decision' => 'accepted',
        'justification' => 'Se verificó el ejercicio correcto.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.files.preview', [$file->budget, $file]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can_confirm', true)
            ->where('decision_warnings.data.0.decision.status', 'accepted'));

    $this->actingAs($manager)->post($confirmRoute, [
        'analysis_revision' => $file->analysis_revision,
    ])->assertSessionHasNoErrors();

    $decision = $issue->decisions()->sole();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($decision->resolved_by)->toBe($manager->id)
        ->and($decision->resolved_at)->not->toBeNull();
});

dataset('supporting confirmation formats', [
    'ficha técnica' => [OwnRevenueImportFormat::TechnicalSheet, 'own_revenue_technical_sheet_needs', ['specific_item_code' => '21101', 'amount_cents' => 12500]],
    'combustible' => [OwnRevenueImportFormat::Fuel, 'own_revenue_fuel_plans', ['outbound_destination' => 'Cancún', 'amount_cents' => 107800]],
    'viáticos' => [OwnRevenueImportFormat::TravelExpenses, 'own_revenue_travel_commissions', ['person_name' => 'Persona', 'total_amount_cents' => 211158]],
]);

test('a ready supporting file can be confirmed without inventing an activity', function (OwnRevenueImportFormat $format, string $table, array $expected) {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file, 'source_row_id' => $sourceRowId, 'payload' => $payload] = readySupportingFile($manager, $format);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [
            'budget' => $file->budget,
            'importFile' => $file,
        ]), ['analysis_revision' => $file->analysis_revision])
        ->assertRedirect(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $file->budget,
            'importFile' => $file,
        ]))
        ->assertInertiaFlash('success', 'Archivo confirmado correctamente. Las reglas de actividad disponibles fueron aplicadas.');

    $this->assertDatabaseHas($table, [
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_budget_id' => $file->own_revenue_budget_id,
        'own_revenue_activity_id' => null,
        'source_row_id' => $sourceRowId,
        ...$expected,
    ]);
    if ($format === OwnRevenueImportFormat::TechnicalSheet) {
        $this->assertDatabaseHas($table, [
            'own_revenue_import_file_id' => $file->id,
            'specific_item_name' => 'Materiales de oficina',
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y suministros',
        ]);
    }
    $line = DB::table($table)->where('own_revenue_import_file_id', $file->id)->first();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($line->own_revenue_activity_id)->toBeNull()
        ->and($line->source_row_id)->toBe($sourceRowId);
})->with('supporting confirmation formats');

test('active activity rules are applied while confirming every supporting format', function (OwnRevenueImportFormat $format, string $table) {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, $format);
    $activity = OwnRevenueActivity::factory()->for($file->budget, 'budget')->create(['code' => 'A02']);
    $groupKey = match ($format) {
        OwnRevenueImportFormat::TechnicalSheet => '21101|MATERIAL',
        OwnRevenueImportFormat::Fuel, OwnRevenueImportFormat::TravelExpenses => 'COMISION',
        default => throw new LogicException('Formato no compatible.'),
    };
    $rule = OwnRevenueActivityRule::factory()->recycle([$file->budget, $activity, $manager])->create([
        'format' => $format,
        'group_key' => $groupKey,
        'group_hash' => app(OwnRevenueActivityGroupKey::class)->hash($format, $groupKey),
        'group_payload' => ['label' => $groupKey],
        'justification' => OwnRevenueActivityJustification::DescriptionClassification,
    ]);
    $file = refreshSupportingConfirmationSnapshot($file);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [
        $file->budget, $file,
    ]), ['analysis_revision' => $file->analysis_revision])->assertSessionHasNoErrors();

    $record = DB::table($table)->where('own_revenue_import_file_id', $file->id)->sole();
    $assignment = OwnRevenueActivityAssignment::query()->sole();

    expect($record->own_revenue_activity_id)->toBe($activity->id)
        ->and($assignment->own_revenue_activity_rule_id)->toBe($rule->id)
        ->and($assignment->own_revenue_activity_id)->toBe($activity->id)
        ->and($assignment->previous_activity_id)->toBeNull()
        ->and($assignment->mode)->toBe(OwnRevenueActivityAssignmentMode::AutomaticRule)
        ->and($assignment->assigned_by)->toBe($manager->id)
        ->and($assignment->group_key)->toBe($groupKey);
})->with([
    'ficha técnica' => [OwnRevenueImportFormat::TechnicalSheet, 'own_revenue_technical_sheet_needs'],
    'combustible' => [OwnRevenueImportFormat::Fuel, 'own_revenue_fuel_plans'],
    'viáticos' => [OwnRevenueImportFormat::TravelExpenses, 'own_revenue_travel_commissions'],
]);

test('inactive and foreign activity rules are ignored during confirmation', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::Fuel);
    $activity = OwnRevenueActivity::factory()->for($file->budget, 'budget')->create();
    $groupKey = 'COMISION';
    $groupHash = app(OwnRevenueActivityGroupKey::class)->hash(OwnRevenueImportFormat::Fuel, $groupKey);
    OwnRevenueActivityRule::factory()->recycle([$file->budget, $activity, $manager])->create([
        'format' => OwnRevenueImportFormat::Fuel,
        'group_key' => $groupKey,
        'group_hash' => $groupHash,
        'is_active' => false,
    ]);
    $foreignBudget = OwnRevenueBudget::factory()->create();
    $foreignActivity = OwnRevenueActivity::factory()->for($foreignBudget, 'budget')->create();
    OwnRevenueActivityRule::factory()->recycle([$foreignBudget, $foreignActivity, $manager])->create([
        'format' => OwnRevenueImportFormat::Fuel,
        'group_key' => $groupKey,
        'group_hash' => $groupHash,
    ]);
    $file = refreshSupportingConfirmationSnapshot($file);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [
        $file->budget, $file,
    ]), ['analysis_revision' => $file->analysis_revision])->assertSessionHasNoErrors();

    expect(DB::table('own_revenue_fuel_plans')->where('own_revenue_import_file_id', $file->id)->value('own_revenue_activity_id'))->toBeNull()
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
});

test('an explicit supporting activity takes precedence over an automatic rule', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::TravelExpenses);
    $sourceActivity = OwnRevenueActivity::factory()->for($file->budget, 'budget')->create(['code' => 'A04']);
    $ruleActivity = OwnRevenueActivity::factory()->for($file->budget, 'budget')->create(['code' => 'A02']);
    $groupKey = 'COMISION';
    OwnRevenueActivityRule::factory()->recycle([$file->budget, $ruleActivity, $manager])->create([
        'format' => OwnRevenueImportFormat::TravelExpenses,
        'group_key' => $groupKey,
        'group_hash' => app(OwnRevenueActivityGroupKey::class)->hash(OwnRevenueImportFormat::TravelExpenses, $groupKey),
    ]);
    $normalizedRow = $file->rows()->where('row_kind', 'travel_expenses_normalized_line')->sole();
    $payload = [...$normalizedRow->normalized_payload, 'activityCode' => 'A04'];
    $normalizedRow->update([
        'normalized_payload' => $payload,
        'row_hash' => app(CanonicalJson::class)->hash($payload),
    ]);
    $file = refreshSupportingConfirmationSnapshot($file);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [
        $file->budget, $file,
    ]), ['analysis_revision' => $file->analysis_revision])->assertSessionHasNoErrors();

    $record = DB::table('own_revenue_travel_commissions')
        ->where('own_revenue_import_file_id', $file->id)
        ->sole();

    expect($record->own_revenue_activity_id)->toBe($sourceActivity->id)
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
});

test('an invalid rule activity rolls back records and confirmation', function () {
    Storage::fake('local');
    $manager = supportingConfirmationUser();
    ['file' => $file] = readySupportingFile($manager, OwnRevenueImportFormat::Fuel);
    $foreignBudget = OwnRevenueBudget::factory()->create();
    $foreignActivity = OwnRevenueActivity::factory()->for($foreignBudget, 'budget')->create();
    $groupKey = 'COMISION';
    OwnRevenueActivityRule::factory()->recycle([$file->budget, $foreignActivity, $manager])->create([
        'own_revenue_budget_id' => $file->own_revenue_budget_id,
        'own_revenue_activity_id' => $foreignActivity->id,
        'format' => OwnRevenueImportFormat::Fuel,
        'group_key' => $groupKey,
        'group_hash' => app(OwnRevenueActivityGroupKey::class)->hash(OwnRevenueImportFormat::Fuel, $groupKey),
    ]);
    $file = refreshSupportingConfirmationSnapshot($file);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.supporting.confirm', [
        $file->budget, $file,
    ]), ['analysis_revision' => $file->analysis_revision])->assertSessionHasErrors('file');

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and(DB::table('own_revenue_fuel_plans')->where('own_revenue_import_file_id', $file->id)->count())->toBe(0)
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
});
