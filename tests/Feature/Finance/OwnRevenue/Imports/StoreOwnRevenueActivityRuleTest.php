<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;

function activityRuleUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'activity-rule-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function confirmedActivityRuleFile(
    OwnRevenueBudget $budget,
    OwnRevenueImportFormat $format,
    int $version = 1,
    OwnRevenueImportFileStatus $status = OwnRevenueImportFileStatus::Confirmed,
): OwnRevenueImportFile {
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();

    return OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => $format,
        'detected_format' => $format,
        'version_number' => $version,
        'status' => $status,
        'confirmed_at' => now()->addSeconds($version),
    ]);
}

/** @return array<string, mixed> */
function validActivityRulePayload(
    OwnRevenueImportFile $workSheet,
    OwnRevenueImportFile $supporting,
    OwnRevenueActivity $activity,
    string $groupHash,
): array {
    return [
        'format' => OwnRevenueImportFormat::Fuel->value,
        'group_hash' => $groupHash,
        'activity_id' => $activity->id,
        'justification' => OwnRevenueActivityJustification::DescriptionClassification->value,
        'justification_note' => null,
        'expected_work_sheet_file_id' => $workSheet->id,
        'expected_supporting_file_id' => $supporting->id,
    ];
}

function activityRuleUrl(OwnRevenueBudget $budget): string
{
    return "/finance/own-revenue/budgets/{$budget->id}/imports/reconciliation/rules";
}

test('a finance manager applies and revises an auditable rule only on the current supporting version', function () {
    $manager = activityRuleUser();
    $budget = OwnRevenueBudget::factory()->create();
    $firstActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A01',
        'name' => 'Primera actividad',
    ]);
    $secondActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A02',
        'name' => 'Segunda actividad',
    ]);
    $workSheet = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::WorkSheet);
    $historicalFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel, 1, OwnRevenueImportFileStatus::Replaced);
    $currentFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel, 2);
    $historicalPlan = OwnRevenueFuelPlan::factory()->recycle([$budget, $historicalFile])->create([
        'own_revenue_import_file_id' => $historicalFile->id,
        'reason' => 'Visita técnica',
    ]);
    $currentPlans = OwnRevenueFuelPlan::factory()->count(2)->recycle([$budget, $currentFile])->create([
        'own_revenue_import_file_id' => $currentFile->id,
        'reason' => '  visita   técnica ',
    ]);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);
    $groupKey = $groupKeys->forFuelPlan($currentPlans->first());
    $groupHash = $groupKeys->hash(OwnRevenueImportFormat::Fuel, $groupKey);

    $this->actingAs($manager)
        ->from('/finance/own-revenue/budgets/'.$budget->id.'/imports')
        ->post(activityRuleUrl($budget), validActivityRulePayload($workSheet, $currentFile, $firstActivity, $groupHash))
        ->assertRedirect('/finance/own-revenue/budgets/'.$budget->id.'/imports')
        ->assertSessionHasNoErrors();

    $firstRule = OwnRevenueActivityRule::query()->sole();
    expect($firstRule->is_active)->toBeTrue()
        ->and($firstRule->group_key)->toBe($groupKey)
        ->and($firstRule->group_hash)->toBe($groupHash)
        ->and($firstRule->group_payload)->toBe(['reason' => 'visita técnica'])
        ->and($firstRule->activity_code)->toBe('A01')
        ->and($firstRule->activity_name)->toBe('Primera actividad')
        ->and($firstRule->created_by)->toBe($manager->id)
        ->and($historicalPlan->fresh()->own_revenue_activity_id)->toBeNull()
        ->and($currentPlans->pluck('id')->map(fn (int $id): ?int => OwnRevenueFuelPlan::query()->findOrFail($id)->own_revenue_activity_id)->all())
        ->toBe([$firstActivity->id, $firstActivity->id]);

    $firstAssignments = OwnRevenueActivityAssignment::query()->orderBy('id')->get();
    expect($firstAssignments)->toHaveCount(2)
        ->and($firstAssignments->pluck('previous_activity_id')->all())->toBe([null, null])
        ->and($firstAssignments->pluck('activity_code')->all())->toBe(['A01', 'A01'])
        ->and($firstAssignments->every(fn (OwnRevenueActivityAssignment $assignment): bool => $assignment->assigned_by === $manager->id))->toBeTrue();

    $this->actingAs($manager)->post(activityRuleUrl($budget), [
        ...validActivityRulePayload($workSheet, $currentFile, $secondActivity, $groupHash),
        'justification' => OwnRevenueActivityJustification::AdministrativeCriterion->value,
        'justification_note' => 'Revisión administrativa.',
    ])->assertSessionHasNoErrors();

    $firstRule->refresh();
    $replacementRule = OwnRevenueActivityRule::query()->whereKeyNot($firstRule->id)->sole();
    expect($firstRule->is_active)->toBeFalse()
        ->and($firstRule->deactivated_by)->toBe($manager->id)
        ->and($firstRule->deactivated_at)->not->toBeNull()
        ->and($replacementRule->is_active)->toBeTrue()
        ->and($replacementRule->replaces_rule_id)->toBe($firstRule->id)
        ->and($replacementRule->own_revenue_activity_id)->toBe($secondActivity->id)
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(4)
        ->and(OwnRevenueActivityAssignment::query()->latest('id')->take(2)->pluck('previous_activity_id')->all())
        ->toBe([$firstActivity->id, $firstActivity->id])
        ->and($historicalPlan->fresh()->own_revenue_activity_id)->toBeNull();
});

test('other justification requires a note', function () {
    $manager = activityRuleUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create(['own_revenue_import_file_id' => $fuelFile->id]);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);
    $groupHash = $groupKeys->hash(OwnRevenueImportFormat::Fuel, $groupKeys->forFuelPlan($plan));

    $this->actingAs($manager)->post(activityRuleUrl($budget), [
        ...validActivityRulePayload($workSheet, $fuelFile, $activity, $groupHash),
        'justification' => OwnRevenueActivityJustification::Other->value,
    ])->assertSessionHasErrors('justification_note');

    expect(OwnRevenueActivityRule::query()->count())->toBe(0)
        ->and($plan->fresh()->own_revenue_activity_id)->toBeNull();
});

test('an activity from another budget is rejected without mutation', function () {
    $manager = activityRuleUser();
    $budget = OwnRevenueBudget::factory()->create();
    $foreignActivity = OwnRevenueActivity::factory()->create();
    $workSheet = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create(['own_revenue_import_file_id' => $fuelFile->id]);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);
    $groupHash = $groupKeys->hash(OwnRevenueImportFormat::Fuel, $groupKeys->forFuelPlan($plan));

    $this->actingAs($manager)
        ->post(activityRuleUrl($budget), validActivityRulePayload($workSheet, $fuelFile, $foreignActivity, $groupHash))
        ->assertSessionHasErrors('activity_id');

    expect(OwnRevenueActivityRule::query()->count())->toBe(0)
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0)
        ->and($plan->fresh()->own_revenue_activity_id)->toBeNull();
});

test('stale confirmed file ids roll back the whole operation', function (string $staleField) {
    $manager = activityRuleUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create(['own_revenue_import_file_id' => $fuelFile->id]);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);
    $groupHash = $groupKeys->hash(OwnRevenueImportFormat::Fuel, $groupKeys->forFuelPlan($plan));
    $payload = validActivityRulePayload($workSheet, $fuelFile, $activity, $groupHash);
    confirmedActivityRuleFile(
        $budget,
        $staleField === 'expected_work_sheet_file_id' ? OwnRevenueImportFormat::WorkSheet : OwnRevenueImportFormat::Fuel,
        2,
    );

    $this->actingAs($manager)
        ->post(activityRuleUrl($budget), $payload)
        ->assertSessionHasErrors([$staleField => 'Los archivos confirmados cambiaron; actualiza la página antes de continuar.']);

    expect(OwnRevenueActivityRule::query()->count())->toBe(0)
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0)
        ->and($plan->fresh()->own_revenue_activity_id)->toBeNull();
})->with(['expected_work_sheet_file_id', 'expected_supporting_file_id']);

test('a consultation role cannot apply activity rules', function () {
    $assistant = activityRuleUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityRuleFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create(['own_revenue_import_file_id' => $fuelFile->id]);
    $groupKeys = app(OwnRevenueActivityGroupKey::class);
    $groupHash = $groupKeys->hash(OwnRevenueImportFormat::Fuel, $groupKeys->forFuelPlan($plan));

    $this->actingAs($assistant)
        ->post(activityRuleUrl($budget), validActivityRulePayload($workSheet, $fuelFile, $activity, $groupHash))
        ->assertForbidden();

    expect(OwnRevenueActivityRule::query()->count())->toBe(0)
        ->and($plan->fresh()->own_revenue_activity_id)->toBeNull();
});

test('an unauthenticated user is redirected to login', function () {
    $budget = OwnRevenueBudget::factory()->create();

    $this->post(activityRuleUrl($budget))->assertRedirect(route('login'));
});
