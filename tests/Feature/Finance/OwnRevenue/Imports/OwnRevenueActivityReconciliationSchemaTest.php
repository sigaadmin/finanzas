<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;

test('activity reconciliation rules and assignments preserve an auditable history', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A03-A01',
        'name' => 'Servicios escolares',
    ]);
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();
    $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'detected_format' => OwnRevenueImportFormat::TechnicalSheet,
        'status' => OwnRevenueImportFileStatus::Confirmed,
    ]);
    $expenseClassification = ExpenseClassification::query()->create([
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
    $need = OwnRevenueTechnicalSheetNeed::factory()->recycle([$budget, $file])->for($file, 'file')->create([
        'own_revenue_activity_id' => null,
        'expense_classification_id' => $expenseClassification->id,
    ]);
    $rule = OwnRevenueActivityRule::factory()->recycle([$budget, $activity])->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'group_key' => 'specific_item_code:21101',
        'group_hash' => str_repeat('a', 64),
        'group_payload' => ['specific_item_code' => '21101'],
        'justification' => OwnRevenueActivityJustification::DescriptionClassification,
    ]);
    $assignment = OwnRevenueActivityAssignment::factory()
        ->for($need, 'assignable')
        ->recycle([$budget, $activity, $file, $rule])
        ->create([
            'previous_activity_id' => null,
            'justification' => OwnRevenueActivityJustification::DescriptionClassification,
        ]);

    expect($rule->budget->is($budget))->toBeTrue()
        ->and($rule->activity->is($activity))->toBeTrue()
        ->and($rule->activity_code)->toBe($activity->code)
        ->and($rule->activity_name)->toBe($activity->name)
        ->and($assignment->budget->is($budget))->toBeTrue()
        ->and($assignment->file->is($file))->toBeTrue()
        ->and($assignment->rule->is($rule))->toBeTrue()
        ->and($assignment->activity->is($activity))->toBeTrue()
        ->and($assignment->activity_code)->toBe($activity->code)
        ->and($assignment->activity_name)->toBe($activity->name)
        ->and($assignment->group_key)->toBe($rule->group_key)
        ->and($assignment->group_hash)->toBe($rule->group_hash)
        ->and($assignment->assignable->is($need))->toBeTrue()
        ->and($need->activityAssignments->sole()->is($assignment))->toBeTrue()
        ->and($budget->activityRules->sole()->is($rule))->toBeTrue()
        ->and($budget->activityAssignments->sole()->is($assignment))->toBeTrue()
        ->and($rule->format)->toBe(OwnRevenueImportFormat::TechnicalSheet)
        ->and($rule->group_payload)->toBe(['specific_item_code' => '21101'])
        ->and($rule->justification)->toBe(OwnRevenueActivityJustification::DescriptionClassification)
        ->and($rule->is_active)->toBeTrue()
        ->and($assignment->mode)->toBe(OwnRevenueActivityAssignmentMode::GroupRule)
        ->and($assignment->justification)->toBe(OwnRevenueActivityJustification::DescriptionClassification)
        ->and($assignment->assigned_at)->not->toBeNull();
});
