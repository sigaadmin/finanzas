<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function workspaceBoundsUser(): User
{
    $user = User::factory()->create([
        'email' => 'workspace-bounds-'.fake()->uuid().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    return $user;
}

test('workspace bounds version history and selected issue details while retaining sql counts', function () {
    $manager = workspaceBoundsUser();
    $budget = OwnRevenueBudget::factory()->create();
    $session = OwnRevenueImportSession::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'created_by' => $manager->id,
    ]);
    $current = null;

    foreach (range(1, 15) as $version) {
        $current = OwnRevenueImportFile::factory()->create([
            'own_revenue_import_session_id' => $session->id,
            'own_revenue_budget_id' => $budget->id,
            'format' => OwnRevenueImportFormat::Abpre,
            'version_number' => $version,
            'status' => $version === 15
                ? OwnRevenueImportFileStatus::Ready
                : OwnRevenueImportFileStatus::Replaced,
        ]);
    }

    foreach (OwnRevenueImportIssueSeverity::cases() as $severity) {
        foreach (range(1, 25) as $index) {
            OwnRevenueImportIssue::factory()->create([
                'own_revenue_import_file_id' => $current->id,
                'severity' => $severity,
                'code' => "bounded.{$severity->value}.{$index}",
                'context' => [
                    'detected_year' => 2030,
                    'exception' => '/private/storage/secret.xlsx',
                ],
            ]);
        }
    }

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/imports/show', false)
            ->has('slots', 5)
            ->has('slots.0.versions', 10)
            ->where('slots.0.versions_total', 15)
            ->where('slots.0.versions_current_page', 1)
            ->where('slots.0.versions_has_more', true)
            ->where('slots.0.versions.0.id', $current->id)
            ->where('slots.0.versions.0.issue_counts.error', 25)
            ->where('slots.0.versions.0.issue_counts.warning', 25)
            ->where('slots.0.versions.0.issue_counts.info', 25)
            ->where('selected_file.id', $current->id)
            ->has('selected_file.issues.data', 50)
            ->where('selected_file.issues.total', 75)
            ->where('selected_file.issues.current_page', 1)
            ->where('selected_file.issues.has_more', true)
            ->where('selected_file.issues.data.0.context.detected_year', 2030)
            ->missing('selected_file.issues.data.0.context.exception')
            ->missing('slots.0.versions.0.issues'));
});
