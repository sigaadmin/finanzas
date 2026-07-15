<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetMonth;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityReconciliationViewData;

function reconciliationFile(
    OwnRevenueBudget $budget,
    OwnRevenueImportFormat $format,
    int $version = 1,
    OwnRevenueImportFileStatus $status = OwnRevenueImportFileStatus::Confirmed,
): OwnRevenueImportFile {
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();

    return OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'format' => $format,
        'detected_format' => $format,
        'version_number' => $version,
        'status' => $status,
        'confirmed_at' => $status === OwnRevenueImportFileStatus::Confirmed ? now()->addSeconds($version) : null,
    ]);
}

function reconciliationClassification(OwnRevenueBudget $budget, string $specificItemCode): ExpenseClassification
{
    return ExpenseClassification::query()->firstOrCreate([
        'fiscal_year' => $budget->fiscal_year,
        'specific_item_code' => $specificItemCode,
    ], [
        'chapter_code' => substr($specificItemCode, 0, 1).'000',
        'chapter_name' => 'Capítulo de prueba',
        'concept_code' => substr($specificItemCode, 0, 2).'00',
        'concept_name' => 'Concepto de prueba',
        'generic_item_code' => substr($specificItemCode, 0, 3).'00',
        'generic_item_name' => 'Partida genérica de prueba',
        'specific_item_name' => 'Partida '.$specificItemCode,
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}

function reconciliationWorkSheetLine(
    OwnRevenueBudget $budget,
    OwnRevenueImportFile $file,
    OwnRevenueActivity $activity,
    string $specificItemCode,
    string $annualAmountCents,
    array $months,
): OwnRevenueWorkSheetLine {
    $line = OwnRevenueWorkSheetLine::factory()->recycle([$budget, $file, $activity])->create([
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_activity_id' => $activity->id,
        'expense_classification_id' => reconciliationClassification($budget, $specificItemCode)->id,
        'activity_code' => $activity->code,
        'activity_name' => $activity->name,
        'specific_item_code' => $specificItemCode,
        'annual_amount_cents' => $annualAmountCents,
    ]);

    foreach ($months as $month => $amountCents) {
        OwnRevenueWorkSheetMonth::factory()->for($line, 'line')->create([
            'month' => $month,
            'amount_cents' => $amountCents,
        ]);
    }

    return $line;
}

/** @return array<string, mixed> */
function reconciliationPersistentState(OwnRevenueBudget $budget): array
{
    return [
        'files' => $budget->importFiles()->orderBy('id')->get()->map(fn (OwnRevenueImportFile $file): array => [
            'id' => $file->id,
            'status' => $file->status->value,
            'replaced_by_file_id' => $file->replaced_by_file_id,
        ])->all(),
        'rules' => $budget->activityRules()->orderBy('id')->get()->map(fn (OwnRevenueActivityRule $rule): array => [
            'id' => $rule->id,
            'activity_id' => $rule->own_revenue_activity_id,
            'is_active' => $rule->is_active,
        ])->all(),
        'assignments' => $budget->activityAssignments()->orderBy('id')->get()->map(fn (OwnRevenueActivityAssignment $assignment): array => [
            'id' => $assignment->id,
            'activity_id' => $assignment->own_revenue_activity_id,
            'assignable_type' => $assignment->assignable_type,
            'assignable_id' => $assignment->assignable_id,
        ])->all(),
        'technical_activity_ids' => $budget->technicalSheetNeeds()->orderBy('id')->pluck('own_revenue_activity_id', 'id')->all(),
        'fuel_activity_ids' => $budget->fuelPlans()->orderBy('id')->pluck('own_revenue_activity_id', 'id')->all(),
        'travel_activity_ids' => $budget->travelCommissions()->orderBy('id')->pluck('own_revenue_activity_id', 'id')->all(),
    ];
}

test('it groups current supporting records and builds exact reconciliation candidates and amounts', function () {
    $budget = OwnRevenueBudget::factory()->create(['fuel_budget_month' => 4]);
    $activityA02 = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A02',
        'name' => 'Formación y actualización',
        'sort_order' => 2,
    ]);
    $activityA04 = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A04',
        'name' => 'Vinculación institucional',
        'sort_order' => 4,
    ]);
    $activityA03 = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A03',
        'name' => 'Desarrollo académico',
        'sort_order' => 3,
    ]);

    $oldWorkSheet = reconciliationFile($budget, OwnRevenueImportFormat::WorkSheet, 1);
    reconciliationWorkSheetLine($budget, $oldWorkSheet, $activityA02, '26101', '999999', [4 => '999999']);
    $workSheet = reconciliationFile($budget, OwnRevenueImportFormat::WorkSheet, 2);

    reconciliationWorkSheetLine($budget, $workSheet, $activityA02, '26101', '120000', [4 => '120000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA04, '26101', '80000', [4 => '80000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA02, '37501', '50000', [4 => '50000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA04, '37501', '70000', [4 => '70000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA04, '37101', '30000', [4 => '30000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA02, '21101', '40000', [4 => '40000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA03, '21101', '25000', [4 => '25000']);
    reconciliationWorkSheetLine($budget, $workSheet, $activityA04, '21101', '60000', [4 => '0', 5 => '60000']);

    $oldFuelFile = reconciliationFile($budget, OwnRevenueImportFormat::Fuel, 1);
    OwnRevenueFuelPlan::factory()->recycle([$budget, $oldFuelFile])->create([
        'own_revenue_import_file_id' => $oldFuelFile->id,
        'reason' => 'Visita técnica',
        'amount_cents' => '999999',
    ]);
    $fuelFile = reconciliationFile($budget, OwnRevenueImportFormat::Fuel, 2);
    $firstFuelPlan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
        'reason' => '  Visita   técnica ',
        'amount_cents' => '100000',
    ]);
    OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
        'reason' => 'VISITA TECNICA',
        'amount_cents' => '50000',
    ]);
    $fuelRule = OwnRevenueActivityRule::factory()->recycle([$budget, $activityA02])->create([
        'format' => OwnRevenueImportFormat::Fuel,
        'group_key' => 'VISITA TECNICA',
        'group_payload' => ['reason' => 'Visita técnica'],
    ]);
    $firstFuelPlan->update(['own_revenue_activity_id' => $activityA02->id]);
    OwnRevenueActivityAssignment::factory()
        ->for($firstFuelPlan, 'assignable')
        ->recycle([$budget, $activityA02, $fuelFile, $fuelRule])
        ->create();

    $technicalFile = reconciliationFile($budget, OwnRevenueImportFormat::TechnicalSheet);
    $firstNeed = OwnRevenueTechnicalSheetNeed::factory()->recycle([$budget, $technicalFile])->create([
        'own_revenue_import_file_id' => $technicalFile->id,
        'expense_classification_id' => reconciliationClassification($budget, '21101')->id,
        'specific_item_code' => '21101',
        'description' => '  Lápices   azules ',
        'amount_cents' => '20000',
        'budget_month' => 4,
    ]);
    OwnRevenueTechnicalSheetNeed::factory()->recycle([$budget, $technicalFile])->create([
        'own_revenue_import_file_id' => $technicalFile->id,
        'expense_classification_id' => reconciliationClassification($budget, '21101')->id,
        'specific_item_code' => '21101',
        'description' => 'LAPICES AZULES',
        'amount_cents' => '15000',
        'budget_month' => 4,
    ]);

    $travelFile = reconciliationFile($budget, OwnRevenueImportFormat::TravelExpenses);
    $commission = OwnRevenueTravelCommission::factory()->recycle([$budget, $travelFile])->create([
        'own_revenue_import_file_id' => $travelFile->id,
        'reason' => 'Reunión académica',
        'per_diem_amount_cents' => '50000',
        'lodging_amount_cents' => '40000',
        'total_amount_cents' => '90000',
        'flight_amount_cents' => '30000',
    ]);

    $stateBefore = reconciliationPersistentState($budget);
    $data = app(OwnRevenueActivityReconciliationViewData::class)->forBudget($budget);
    $stateAfter = reconciliationPersistentState($budget);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);

    expect($data['summary'])->toBe([
        'total' => 5,
        'assigned' => 1,
        'pending' => 4,
        'complete' => false,
    ])->and($data['snapshots'])->toBe([
        'work_sheet_file_id' => $workSheet->id,
        'supporting_file_ids' => [
            'technical_sheet' => $technicalFile->id,
            'fuel' => $fuelFile->id,
            'travel_expenses' => $travelFile->id,
        ],
    ])->and($data['formats']['fuel']['summary'])->toBe([
        'total' => 2,
        'assigned' => 1,
        'pending' => 1,
        'complete' => false,
    ])->and($data['formats']['fuel']['groups'])->toHaveCount(1)
        ->and($data['formats']['fuel']['groups'][0]['record_count'])->toBe(2)
        ->and($data['formats']['fuel']['groups'][0]['label'])->toBe('Visita técnica')
        ->and($data['formats']['fuel']['groups'][0]['candidate_activity_codes'])->toBe(['A02', 'A04'])
        ->and($data['formats']['fuel']['groups'][0]['current_activity']['code'])->toBe('A02')
        ->and($data['formats']['fuel']['groups'][0]['records'][0]['latest_assignment']['activity_code'])->toBe('A02')
        ->and($data['formats']['fuel']['groups'][0]['active_rule']['activity']['code'])->toBe('A02')
        ->and($data['formats']['fuel']['groups'][0]['hash'])->toBe(
            hash('sha256', 'fuel|VISITA TECNICA'),
        )->and($data['formats']['fuel']['detail_cents'])->toBe('150000')
        ->and($data['formats']['fuel']['work_sheet_cents'])->toBe('200000')
        ->and($data['formats']['fuel']['difference_cents'])->toBe('-50000')
        ->and($data['formats']['technical_sheet']['groups'])->toHaveCount(1)
        ->and($data['formats']['technical_sheet']['groups'][0]['record_count'])->toBe(2)
        ->and($data['formats']['technical_sheet']['groups'][0]['candidate_activity_codes'])->toBe(['A02', 'A03'])
        ->and($data['formats']['technical_sheet']['groups'][0]['month_evidence'])->toBe([4])
        ->and($data['formats']['technical_sheet']['detail_cents'])->toBe('35000')
        ->and($data['formats']['technical_sheet']['work_sheet_cents'])->toBe('65000')
        ->and($data['formats']['technical_sheet']['difference_cents'])->toBe('-30000')
        ->and($data['formats']['travel_expenses']['groups'])->toHaveCount(1)
        ->and($data['formats']['travel_expenses']['groups'][0]['candidate_activity_codes'])->toBe(['A02', 'A04'])
        ->and($data['formats']['travel_expenses']['detail_cents'])->toBe('120000')
        ->and($data['formats']['travel_expenses']['work_sheet_cents'])->toBe('150000')
        ->and($data['formats']['travel_expenses']['difference_cents'])->toBe('-30000')
        ->and($groupKeys->forTechnicalSheetNeed($firstNeed))->toBe('21101|LAPICES AZULES')
        ->and($groupKeys->forFuelPlan($firstFuelPlan))->toBe('VISITA TECNICA')
        ->and($groupKeys->forTravelCommission($commission))->toBe('REUNION ACADEMICA')
        ->and($groupKeys->hash(OwnRevenueImportFormat::Fuel, 'VISITA TECNICA'))->toBe(
            hash('sha256', 'fuel|VISITA TECNICA'),
        )->and($stateAfter)->toBe($stateBefore);
});

test('it returns deterministic empty states without persisting reconciliation data', function () {
    $budget = OwnRevenueBudget::factory()->create();

    $data = app(OwnRevenueActivityReconciliationViewData::class)->forBudget($budget);

    expect($data['summary'])->toBe([
        'total' => 0,
        'assigned' => 0,
        'pending' => 0,
        'complete' => false,
    ])->and($data['empty_reasons'])->toBe([
        'work_sheet' => 'Confirma una Hoja de trabajo antes de conciliar actividades.',
        'supporting' => 'No hay archivos complementarios confirmados para conciliar.',
    ])->and($budget->activityRules()->count())->toBe(0)
        ->and($budget->activityAssignments()->count())->toBe(0);
});
