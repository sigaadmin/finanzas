<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    $contents = 'supporting-'.$format->value;
    $revision = (string) Str::uuid();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => $format,
        'detected_format' => $format,
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

    return ['file' => $file->fresh(), 'source_row_id' => $source->id, 'payload' => $payload];
}

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
        ->assertInertiaFlash('success', 'Archivo confirmado correctamente. La actividad se asignará durante la conciliación.');

    $this->assertDatabaseHas($table, [
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_budget_id' => $file->own_revenue_budget_id,
        'own_revenue_activity_id' => null,
        'source_row_id' => $sourceRowId,
        ...$expected,
    ]);
    $line = DB::table($table)->where('own_revenue_import_file_id', $file->id)->first();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($line->own_revenue_activity_id)->toBeNull()
        ->and($line->source_row_id)->toBe($sourceRowId);
})->with('supporting confirmation formats');
