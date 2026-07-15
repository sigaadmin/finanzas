<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
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

function activityExceptionUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'activity-exception-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function confirmedActivityExceptionFile(
    OwnRevenueBudget $budget,
    OwnRevenueImportFormat $format,
    int $version = 1,
    OwnRevenueImportFileStatus $status = OwnRevenueImportFileStatus::Confirmed,
): OwnRevenueImportFile {
    return OwnRevenueImportFile::factory()
        ->for(OwnRevenueImportSession::factory()->for($budget, 'budget'), 'session')
        ->create([
            'own_revenue_budget_id' => $budget->id,
            'format' => $format,
            'detected_format' => $format,
            'version_number' => $version,
            'status' => $status,
            'confirmed_at' => now()->addSeconds($version),
        ]);
}

/** @return array<string, mixed> */
function validActivityExceptionPayload(
    OwnRevenueImportFile $workSheet,
    OwnRevenueImportFile $supporting,
    OwnRevenueActivity $activity,
): array {
    return [
        'format' => $supporting->format->value,
        'activity_id' => $activity->id,
        'justification' => OwnRevenueActivityJustification::AdministrativeCriterion->value,
        'justification_note' => 'Excepción revisada individualmente.',
        'expected_work_sheet_file_id' => $workSheet->id,
        'expected_supporting_file_id' => $supporting->id,
    ];
}

function activityExceptionUrl(OwnRevenueBudget $budget, int $recordId): string
{
    return "/finance/own-revenue/budgets/{$budget->id}/imports/reconciliation/records/{$recordId}/activity";
}

test('a manager records append-only individual exceptions without changing the active group rule', function () {
    $manager = activityExceptionUser();
    $budget = OwnRevenueBudget::factory()->create();
    $previousActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A01']);
    $newActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A02']);
    $thirdActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A03']);
    $workSheet = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
        'own_revenue_activity_id' => $previousActivity->id,
        'reason' => 'Visita técnica',
    ]);
    $otherPlan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
        'own_revenue_activity_id' => $previousActivity->id,
        'reason' => 'Visita técnica',
    ]);
    $rule = OwnRevenueActivityRule::factory()->recycle([$budget, $previousActivity, $manager])->create([
        'format' => OwnRevenueImportFormat::Fuel,
        'group_key' => 'visita técnica',
        'group_payload' => ['reason' => 'Visita técnica'],
        'is_active' => true,
    ]);
    $ruleUpdatedAt = $rule->updated_at;

    $this->actingAs($manager)
        ->from('/finance/own-revenue/budgets/'.$budget->id.'/imports')
        ->post(activityExceptionUrl($budget, $plan->id), validActivityExceptionPayload($workSheet, $fuelFile, $newActivity))
        ->assertRedirect('/finance/own-revenue/budgets/'.$budget->id.'/imports')
        ->assertSessionHasNoErrors();

    $assignment = OwnRevenueActivityAssignment::query()->sole();
    $rule->refresh();
    expect($plan->fresh()->own_revenue_activity_id)->toBe($newActivity->id)
        ->and($otherPlan->fresh()->own_revenue_activity_id)->toBe($previousActivity->id)
        ->and($assignment->rule)->toBeNull()
        ->and($assignment->mode)->toBe(OwnRevenueActivityAssignmentMode::IndividualException)
        ->and($assignment->previous_activity_id)->toBe($previousActivity->id)
        ->and($assignment->own_revenue_activity_id)->toBe($newActivity->id)
        ->and($assignment->activity_code)->toBe($newActivity->code)
        ->and($assignment->activity_name)->toBe($newActivity->name)
        ->and($assignment->own_revenue_import_file_id)->toBe($fuelFile->id)
        ->and($assignment->group_key)->toBe('VISITA TECNICA')
        ->and($assignment->group_hash)->toBe(hash('sha256', 'fuel|VISITA TECNICA'))
        ->and($assignment->assigned_by)->toBe($manager->id)
        ->and($assignment->assigned_at)->not->toBeNull()
        ->and($rule->is_active)->toBeTrue()
        ->and($rule->updated_at->equalTo($ruleUpdatedAt))->toBeTrue()
        ->and($rule->deactivated_at)->toBeNull();

    $this->actingAs($manager)->post(
        activityExceptionUrl($budget, $plan->id),
        validActivityExceptionPayload($workSheet, $fuelFile, $thirdActivity),
    )->assertSessionHasNoErrors();

    $rule->refresh();
    expect(OwnRevenueActivityAssignment::query()->count())->toBe(2)
        ->and(OwnRevenueActivityAssignment::query()->latest('id')->firstOrFail()->previous_activity_id)->toBe($newActivity->id)
        ->and($plan->fresh()->own_revenue_activity_id)->toBe($thirdActivity->id)
        ->and($rule->is_active)->toBeTrue()
        ->and($rule->updated_at->equalTo($ruleUpdatedAt))->toBeTrue()
        ->and($rule->deactivated_at)->toBeNull()
        ->and(OwnRevenueActivityRule::query()->count())->toBe(1);
});

test('it resolves the scalar record id only in the validated current format and budget', function (string $case) {
    $manager = activityExceptionUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel);
    $currentPlan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
    ]);

    $payload = validActivityExceptionPayload($workSheet, $fuelFile, $activity);
    if ($case === 'replaced version') {
        $oldFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel, 0, OwnRevenueImportFileStatus::Replaced);
        $rejectedRecord = OwnRevenueFuelPlan::factory()->recycle([$budget, $oldFile])->create([
            'own_revenue_import_file_id' => $oldFile->id,
        ]);
        $recordId = $rejectedRecord->id;
    } elseif ($case === 'different format table') {
        $travelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::TravelExpenses);
        $rejectedRecord = $currentPlan;
        $recordId = $currentPlan->id;
        $payload = validActivityExceptionPayload($workSheet, $travelFile, $activity);
    } else {
        $foreignBudget = OwnRevenueBudget::factory()->create();
        $foreignFile = confirmedActivityExceptionFile($foreignBudget, OwnRevenueImportFormat::Fuel);
        $rejectedRecord = OwnRevenueFuelPlan::factory()->recycle([$foreignBudget, $foreignFile])->create([
            'own_revenue_import_file_id' => $foreignFile->id,
        ]);
        $recordId = $rejectedRecord->id;
    }

    $this->actingAs($manager)
        ->post(activityExceptionUrl($budget, $recordId), $payload)
        ->assertSessionHasErrors('record');

    expect(OwnRevenueActivityAssignment::query()->count())->toBe(0)
        ->and($currentPlan->fresh()->own_revenue_activity_id)->toBeNull()
        ->and($rejectedRecord->fresh()->own_revenue_activity_id)->toBeNull();
})->with(['replaced version', 'different format table', 'different budget']);

test('validation and ownership failures do not mutate a record', function (string $case) {
    $manager = activityExceptionUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = $case === 'foreign activity'
        ? OwnRevenueActivity::factory()->create()
        : OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
    ]);
    $payload = validActivityExceptionPayload($workSheet, $fuelFile, $activity);
    if ($case === 'other without note') {
        $payload['justification'] = OwnRevenueActivityJustification::Other->value;
        unset($payload['justification_note']);
    }

    $this->actingAs($manager)
        ->post(activityExceptionUrl($budget, $plan->id), $payload)
        ->assertSessionHasErrors($case === 'foreign activity' ? 'activity_id' : 'justification_note');

    expect($plan->fresh()->own_revenue_activity_id)->toBeNull()
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
})->with(['other without note', 'foreign activity']);

test('stale confirmed snapshots roll back an individual exception', function (string $field) {
    $manager = activityExceptionUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
    ]);
    $payload = validActivityExceptionPayload($workSheet, $fuelFile, $activity);
    confirmedActivityExceptionFile(
        $budget,
        $field === 'expected_work_sheet_file_id' ? OwnRevenueImportFormat::WorkSheet : OwnRevenueImportFormat::Fuel,
        2,
    );

    $this->actingAs($manager)
        ->post(activityExceptionUrl($budget, $plan->id), $payload)
        ->assertSessionHasErrors([$field => 'Los archivos confirmados cambiaron; actualiza la página antes de continuar.']);

    expect($plan->fresh()->own_revenue_activity_id)->toBeNull()
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
})->with(['expected_work_sheet_file_id', 'expected_supporting_file_id']);

test('consultation and guest access cannot record an exception', function () {
    $assistant = activityExceptionUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $workSheet = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::WorkSheet);
    $fuelFile = confirmedActivityExceptionFile($budget, OwnRevenueImportFormat::Fuel);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuelFile])->create([
        'own_revenue_import_file_id' => $fuelFile->id,
    ]);

    $this->actingAs($assistant)
        ->post(activityExceptionUrl($budget, $plan->id), validActivityExceptionPayload($workSheet, $fuelFile, $activity))
        ->assertForbidden();

    auth()->logout();
    $this->post(activityExceptionUrl($budget, $plan->id))->assertRedirect(route('login'));

    expect($plan->fresh()->own_revenue_activity_id)->toBeNull()
        ->and(OwnRevenueActivityAssignment::query()->count())->toBe(0);
});
