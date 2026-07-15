<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueActivityGroupKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function activityReconciliationNavigationUser(UserRole $role): User
{
    $email = 'activity-reconciliation-navigation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, work_sheet: OwnRevenueImportFile, fuel: OwnRevenueImportFile, group_hash: string} */
function activityReconciliationNavigationFixture(): array
{
    $budget = OwnRevenueBudget::factory()->create();
    $workSheet = OwnRevenueImportFile::factory()->for($budget, 'budget')->create([
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'version_number' => 1,
        'confirmed_at' => now(),
    ]);
    $fuel = OwnRevenueImportFile::factory()->for($budget, 'budget')->create([
        'format' => OwnRevenueImportFormat::Fuel,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'version_number' => 1,
        'confirmed_at' => now(),
    ]);
    $plan = OwnRevenueFuelPlan::factory()->recycle([$budget, $fuel])->create([
        'own_revenue_import_file_id' => $fuel->id,
        'reason' => 'Visita técnica',
    ]);
    OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A02']);
    $keys = app(OwnRevenueActivityGroupKey::class);

    return [
        'budget' => $budget,
        'work_sheet' => $workSheet,
        'fuel' => $fuel,
        'group_hash' => $keys->hash(OwnRevenueImportFormat::Fuel, $keys->forFuelPlan($plan)),
    ];
}

test('managers can open reconciliation and select a group without exposing other group records', function () {
    $manager = activityReconciliationNavigationUser(UserRole::FinanceManager);
    ['budget' => $budget, 'work_sheet' => $workSheet, 'fuel' => $fuel, 'group_hash' => $groupHash] = activityReconciliationNavigationFixture();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.reconciliation.show', [
            'budget' => $budget,
            'format' => 'fuel',
            'group' => $groupHash,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/reconciliation')
            ->where('budget.id', $budget->id)
            ->where('permissions.manage', true)
            ->where('snapshots.work_sheet_file_id', $workSheet->id)
            ->where('snapshots.supporting_file_ids.fuel', $fuel->id)
            ->where('summary.total', 1)
            ->where('formats.fuel.summary.total', 1)
            ->where('selected_format', 'fuel')
            ->where('groups.data.0.hash', $groupHash)
            ->missing('groups.data.0.records')
            ->where('selected_group.hash', $groupHash)
            ->has('selected_group.records', 1));
});

test('consultation roles see reconciliation without mutation permission', function () {
    $assistant = activityReconciliationNavigationUser(UserRole::FinanceAssistant);
    ['budget' => $budget] = activityReconciliationNavigationFixture();

    $this->actingAs($assistant)
        ->get(route('finance.own-revenue.budgets.imports.reconciliation.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions.manage', false)
            ->where('permissions.view', true));
});

test('unknown group hashes do not expose record details', function () {
    $manager = activityReconciliationNavigationUser(UserRole::FinanceManager);
    ['budget' => $budget] = activityReconciliationNavigationFixture();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.reconciliation.show', [
            'budget' => $budget,
            'format' => 'fuel',
            'group' => str_repeat('f', 64),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selected_group', null)
            ->missing('groups.data.0.records'));
});

test('users without finance access cannot view reconciliation', function () {
    $user = User::factory()->create();
    ['budget' => $budget] = activityReconciliationNavigationFixture();

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.imports.reconciliation.show', $budget))
        ->assertForbidden();
});
