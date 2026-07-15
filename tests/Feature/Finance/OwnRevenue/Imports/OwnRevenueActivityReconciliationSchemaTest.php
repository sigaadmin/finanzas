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
    $replacementActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A03-A02',
        'name' => 'Actualización docente',
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
    $groupKey = '21101|BOLIGRAFO AZUL';
    $expectedGroupHash = hash('sha256', OwnRevenueImportFormat::TechnicalSheet->value.'|'.$groupKey);
    $rule = OwnRevenueActivityRule::factory()->recycle([$budget, $activity])->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'group_key' => $groupKey,
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
    $rule->update([
        'is_active' => false,
        'deactivated_at' => now(),
    ]);
    $replacementRule = OwnRevenueActivityRule::factory()->recycle([$budget, $replacementActivity])->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'group_key' => $groupKey,
        'group_payload' => ['specific_item_code' => '21101'],
        'justification' => OwnRevenueActivityJustification::AdministrativeCriterion,
        'replaces_rule_id' => $rule->id,
    ]);
    $individualAssignment = OwnRevenueActivityAssignment::factory()
        ->for($need, 'assignable')
        ->recycle([$budget, $replacementActivity, $file])
        ->create([
            'own_revenue_budget_id' => $budget->id,
            'own_revenue_import_file_id' => $file->id,
            'own_revenue_activity_rule_id' => null,
            'previous_activity_id' => $activity->id,
            'own_revenue_activity_id' => $replacementActivity->id,
            'activity_code' => $replacementActivity->code,
            'activity_name' => $replacementActivity->name,
            'mode' => OwnRevenueActivityAssignmentMode::IndividualException,
            'group_key' => $groupKey,
            'group_hash' => $expectedGroupHash,
            'justification' => OwnRevenueActivityJustification::AdministrativeCriterion,
            'justification_note' => 'Excepción individual documentada.',
        ]);

    expect($rule->budget->is($budget))->toBeTrue()
        ->and($rule->activity->is($activity))->toBeTrue()
        ->and($rule->activity_code)->toBe($activity->code)
        ->and($rule->activity_name)->toBe($activity->name)
        ->and($rule->group_hash)->toBe($expectedGroupHash)
        ->and($assignment->budget->is($budget))->toBeTrue()
        ->and($assignment->file->is($file))->toBeTrue()
        ->and($assignment->rule->is($rule))->toBeTrue()
        ->and($assignment->activity->is($activity))->toBeTrue()
        ->and($assignment->activity_code)->toBe($activity->code)
        ->and($assignment->activity_name)->toBe($activity->name)
        ->and($assignment->group_key)->toBe($rule->group_key)
        ->and($assignment->group_hash)->toBe($rule->group_hash)
        ->and($assignment->assignable->is($need))->toBeTrue()
        ->and($rule->fresh()->is_active)->toBeFalse()
        ->and($rule->fresh()->deactivated_at)->not->toBeNull()
        ->and($replacementRule->replacesRule->is($rule))->toBeTrue()
        ->and($rule->replacementRules->sole()->is($replacementRule))->toBeTrue()
        ->and($replacementRule->group_hash)->toBe($expectedGroupHash)
        ->and($individualAssignment->rule)->toBeNull()
        ->and($individualAssignment->previousActivity->is($activity))->toBeTrue()
        ->and($individualAssignment->activity->is($replacementActivity))->toBeTrue()
        ->and($individualAssignment->mode)->toBe(OwnRevenueActivityAssignmentMode::IndividualException)
        ->and($need->activityAssignments)->toHaveCount(2)
        ->and($need->activityAssignments->contains(fn (OwnRevenueActivityAssignment $history): bool => $history->is($assignment)))->toBeTrue()
        ->and($need->activityAssignments->contains(fn (OwnRevenueActivityAssignment $history): bool => $history->is($individualAssignment)))->toBeTrue()
        ->and($budget->activityRules)->toHaveCount(2)
        ->and($budget->activityAssignments)->toHaveCount(2)
        ->and($rule->format)->toBe(OwnRevenueImportFormat::TechnicalSheet)
        ->and($rule->group_payload)->toBe(['specific_item_code' => '21101'])
        ->and($rule->justification)->toBe(OwnRevenueActivityJustification::DescriptionClassification)
        ->and($assignment->mode)->toBe(OwnRevenueActivityAssignmentMode::GroupRule)
        ->and($assignment->justification)->toBe(OwnRevenueActivityJustification::DescriptionClassification)
        ->and($assignment->assigned_at)->not->toBeNull();
});
