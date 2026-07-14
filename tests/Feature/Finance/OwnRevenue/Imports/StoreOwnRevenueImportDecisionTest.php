<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;

function importDecisionUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'import-decision-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{OwnRevenueBudget, OwnRevenueImportFile, OwnRevenueImportIssue} */
function pendingAbpreMismatchDecision(): array
{
    $budget = OwnRevenueBudget::factory()->create();
    $abpre = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now()->subMinute(),
    ]);
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales',
        'concept_code' => '2100',
        'concept_name' => 'Administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Insumos',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Papelería',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $abpreLine = OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => 100,
    ]);
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Ready,
        'analyzed_at' => now(),
        'budget_updated_at_at_analysis' => $budget->updated_at,
    ]);
    $issue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'severity' => OwnRevenueImportIssueSeverity::Warning,
        'code' => 'work_sheet.abpre_mismatch',
        'field' => '21101',
        'context' => [
            'specific_item_code' => '21101',
            'work_sheet_total_cents' => '101',
            'abpre_total_cents' => '100',
            'difference_cents' => '1',
            'abpre_import_file_id' => $abpre->id,
            'abpre_line_ids' => [$abpreLine->id],
            'requires_decision' => true,
        ],
    ]);

    return [$budget, $file, $issue];
}

function importDecisionRoute(OwnRevenueBudget $budget, OwnRevenueImportFile $file, OwnRevenueImportIssue $issue): string
{
    return route('finance.own-revenue.budgets.imports.files.issues.decision.store', [
        'budget' => $budget,
        'importFile' => $file,
        'issue' => $issue,
    ]);
}

test('an accepted current mismatch decision is stored and no longer pending', function () {
    $manager = importDecisionUser();
    [$budget, $file, $issue] = pendingAbpreMismatchDecision();

    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $issue), [
        'analysis_revision' => $file->analyzed_at->toISOString(),
        'decision' => 'accepted',
        'justification' => 'La calendarización operativa difiere del techo oficial.',
    ])->assertRedirect();

    $decision = $issue->decisions()->sole();
    expect($decision->resolution)->toBe('accepted')
        ->and($decision->resolved_value)->toMatchArray([
            'accepted' => true,
            'analysis_revision' => $file->analyzed_at->toISOString(),
        ])
        ->and($issue->hasPendingRequiredDecision())->toBeFalse();
});

test('a rejected or absent mismatch decision remains pending', function () {
    $manager = importDecisionUser();
    [$budget, $file, $issue] = pendingAbpreMismatchDecision();

    expect($issue->hasPendingRequiredDecision())->toBeTrue();

    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $issue), [
        'analysis_revision' => $file->analyzed_at->toISOString(),
        'decision' => 'rejected',
        'justification' => null,
    ])->assertRedirect();

    expect($issue->fresh()->decisions()->sole()->resolution)->toBe('rejected')
        ->and($issue->fresh()->hasPendingRequiredDecision())->toBeTrue();
});

test('decision endpoint rejects stale analysis revisions and stale ABPRE snapshots', function () {
    $manager = importDecisionUser();
    [$budget, $file, $issue] = pendingAbpreMismatchDecision();

    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $issue), [
        'analysis_revision' => now()->subDay()->toISOString(),
        'decision' => 'accepted',
    ])->assertSessionHasErrors('analysis_revision');
    expect($issue->decisions()->count())->toBe(0);

    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $issue), [
        'analysis_revision' => $file->analyzed_at->toISOString(),
        'decision' => 'accepted',
    ])->assertRedirect();
    expect($issue->fresh()->hasPendingRequiredDecision())->toBeFalse();

    OwnRevenueImportFile::query()->whereKey($issue->context['abpre_import_file_id'])->update([
        'status' => OwnRevenueImportFileStatus::Replaced,
    ]);
    OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
    expect($issue->fresh()->hasPendingRequiredDecision())->toBeTrue();

    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $issue), [
        'analysis_revision' => $file->analyzed_at->toISOString(),
        'decision' => 'accepted',
    ])->assertSessionHasErrors('analysis_revision');
    expect($issue->decisions()->count())->toBe(1);
});

test('decision endpoint scopes issues to their file and enforces authorization', function () {
    $manager = importDecisionUser();
    $auditor = importDecisionUser(UserRole::FinanceAuditor);
    [$budget, $file, $issue] = pendingAbpreMismatchDecision();
    [, $otherFile, $otherIssue] = pendingAbpreMismatchDecision();
    $payload = ['analysis_revision' => $file->analyzed_at->toISOString(), 'decision' => 'accepted'];

    $this->post(importDecisionRoute($budget, $file, $issue), $payload)->assertRedirect(route('login'));
    $this->actingAs($auditor)->post(importDecisionRoute($budget, $file, $issue), $payload)->assertForbidden();
    $this->actingAs($manager)->post(importDecisionRoute($budget, $file, $otherIssue), $payload)->assertNotFound();
    expect($otherFile->id)->not->toBe($file->id);
});
