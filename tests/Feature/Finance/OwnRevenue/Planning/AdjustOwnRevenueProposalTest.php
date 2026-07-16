<?php

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;
use App\Services\Finance\OwnRevenue\Planning\ProportionalCutSuggestion;
use Inertia\Testing\AssertableInertia as Assert;

function cutUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'proposal-cuts-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function cutClassification(int $year): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $year,
        'chapter_code' => '2000', 'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100', 'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100', 'generic_item_name' => 'Materiales y útiles de oficina',
        'specific_item_code' => '21101', 'specific_item_name' => 'Materiales y útiles de oficina',
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
}

/** @return array{budget: OwnRevenueBudget, proposal: OwnRevenueProposal, manager: User, activity: OwnRevenueActivity, first: OwnRevenueProposalTechnicalNeed, second: OwnRevenueProposalTechnicalNeed, files: array<string, OwnRevenueImportFile>} */
function cutFixture(): array
{
    $manager = cutUser();
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::ProposalCalculated,
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => $manager->id,
        'cog_confirmed_at' => now(),
        'uma_value' => '117.3100', 'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '24.5000', 'fuel_price_status' => AnnualValueStatus::Final,
    ]);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'prepared_by']);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'authorized_by']);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A01', 'name' => 'Actividad uno']);
    $classification = cutClassification($budget->fiscal_year);
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->for($manager, 'createdBy')->create();
    $files = collect(OwnRevenueImportFormat::cases())->mapWithKeys(function (OwnRevenueImportFormat $format) use ($budget, $manager, $session): array {
        $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
            'own_revenue_budget_id' => $budget->id,
            'uploaded_by' => $manager->id,
            'format' => $format,
            'detected_format' => $format,
            'status' => OwnRevenueImportFileStatus::Confirmed,
            'confirmed_by' => $manager->id,
            'confirmed_at' => now(),
        ]);

        return [$format->value => $file];
    })->all();

    $workSheetLine = OwnRevenueWorkSheetLine::factory()->for($budget, 'budget')->for($activity, 'activity')->for($classification, 'expenseClassification')->create([
        'own_revenue_import_file_id' => $files['work_sheet']->id,
        'activity_code' => $activity->code,
        'activity_name' => $activity->name,
        'specific_item_code' => $cut['specific_item_code'] ?? '21101',
        'annual_amount_cents' => 600,
    ]);
    $workSheetLine->months()->create(['month' => 5, 'amount_cents' => 600]);
    $abpreLine = OwnRevenueAbpreLine::factory()->for($budget, 'budget')->for($classification, 'expenseClassification')->create([
        'own_revenue_import_file_id' => $files['abpre']->id,
        'specific_item_code' => $cut['specific_item_code'] ?? '21101',
        'annual_amount_cents' => 600,
    ]);
    $abpreLine->months()->create(['month' => 5, 'amount_cents' => 600]);

    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create([
        'status' => OwnRevenueProposalStatus::Calculated,
        'source_abpre_file_id' => $files['abpre']->id,
        'source_work_sheet_file_id' => $files['work_sheet']->id,
        'source_technical_sheet_file_id' => $files['technical_sheet']->id,
        'source_fuel_file_id' => $files['fuel']->id,
        'source_travel_expenses_file_id' => $files['travel_expenses']->id,
        'source_fingerprint' => $readiness->fingerprint,
        'total_amount_cents' => 1000,
        'calculated_by' => $manager->id,
        'calculated_at' => now(),
    ]);
    $first = OwnRevenueProposalTechnicalNeed::factory()->for($proposal, 'proposal')->for($budget, 'budget')
        ->for($activity, 'activity')->for($classification, 'expenseClassification')->create([
            'stable_key' => 'technical:a', 'budget_amount_cents' => 700,
            'reference_amount_cents' => 700, 'unit_price_cents' => 700,
            'quantity' => '1.0000', 'budget_month' => 5, 'sort_order' => 1,
        ]);
    $second = OwnRevenueProposalTechnicalNeed::factory()->for($proposal, 'proposal')->for($budget, 'budget')
        ->for($activity, 'activity')->for($classification, 'expenseClassification')->create([
            'stable_key' => 'technical:b', 'budget_amount_cents' => 300,
            'reference_amount_cents' => 300, 'unit_price_cents' => 300,
            'quantity' => '1.0000', 'budget_month' => 5, 'sort_order' => 2,
        ]);

    return compact('budget', 'proposal', 'manager', 'activity', 'first', 'second', 'files');
}

test('reconciliation and proportional suggestion are exact and do not persist a preview', function () {
    ['proposal' => $proposal] = cutFixture();

    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal);
    $service = app(ProportionalCutSuggestion::class);
    $suggestion = $service->suggest($reconciliation['groups']);
    $remainderSuggestion = $service->suggest([[
        'required_cut_cents' => '1',
        'candidates' => [
            ['stable_key' => 'technical:b', 'available_amount_cents' => '1'],
            ['stable_key' => 'technical:a', 'available_amount_cents' => '1'],
        ],
    ]]);

    expect($reconciliation['summary'])->toMatchArray([
        'calculated_amount_cents' => '1000',
        'abpre_amount_cents' => '600',
        'required_cut_cents' => '400',
        'distributed_cut_cents' => '0',
        'pending_cut_cents' => '400',
        'adjusted_amount_cents' => '1000',
    ])->and($suggestion)->toBe([
        'technical:a' => '280',
        'technical:b' => '120',
    ])->and($remainderSuggestion)->toBe([
        'technical:a' => '1',
        'technical:b' => '0',
    ])->and(OwnRevenueProposalCut::query()->count())->toBe(0);
});

test('manual or suggested cuts must be compatible and cannot exceed a need or required reduction', function (array $cuts, ?string $error) {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'first' => $first, 'second' => $second] = cutFixture();
    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal);
    $payload = collect($cuts)->map(fn (array $cut): array => [
        ...$cut,
        'target_id' => $cut['stable_key'] === 'technical:a' ? $first->id : $second->id,
        'target_type' => 'technical',
        'specific_item_code' => $cut['specific_item_code'] ?? '21101',
    ])->all();

    $response = $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $reconciliation['fingerprint'],
        'cuts' => $payload,
    ]);

    if ($error === null) {
        $response->assertSessionHasNoErrors();
        expect(OwnRevenueProposalCut::query()->sum('amount_cents'))->toBe(400);
    } else {
        $response->assertSessionHasErrors($error);
        expect(OwnRevenueProposalCut::query()->count())->toBe(0);
    }
})->with([
    'exact suggestion' => [[
        ['stable_key' => 'technical:a', 'amount_cents' => '280'],
        ['stable_key' => 'technical:b', 'amount_cents' => '120'],
    ], null],
    'negative amount' => [[['stable_key' => 'technical:a', 'amount_cents' => '-1']], 'cuts.0.amount_cents'],
    'over available' => [[['stable_key' => 'technical:a', 'amount_cents' => '701']], 'cuts'],
    'over required' => [[['stable_key' => 'technical:a', 'amount_cents' => '401']], 'cuts'],
    'wrong item' => [[['stable_key' => 'technical:a', 'amount_cents' => '100', 'specific_item_code' => '99999']], 'cuts'],
]);

test('an exact distribution creates a separate immutable adjusted snapshot', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'first' => $first, 'second' => $second] = cutFixture();
    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal);
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $reconciliation['fingerprint'],
        'cuts' => [
            ['target_type' => 'technical', 'target_id' => $first->id, 'stable_key' => 'technical:a', 'specific_item_code' => '21101', 'amount_cents' => '280'],
            ['target_type' => 'technical', 'target_id' => $second->id, 'stable_key' => 'technical:b', 'specific_item_code' => '21101', 'amount_cents' => '120'],
        ],
    ])->assertSessionHasNoErrors();
    $current = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.adjust', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $current['fingerprint'],
    ])->assertSessionHasNoErrors();

    $adjusted = OwnRevenueProposal::query()->where('version_number', 2)->sole();
    expect($adjusted->status)->toBe(OwnRevenueProposalStatus::Adjusted)
        ->and($adjusted->based_on_proposal_id)->toBe($proposal->id)
        ->and($adjusted->total_amount_cents)->toBe(600)
        ->and($adjusted->technicalNeeds()->orderBy('sort_order')->pluck('budget_amount_cents')->all())->toBe([420, 180])
        ->and($proposal->fresh()->status)->toBe(OwnRevenueProposalStatus::Calculated)
        ->and($proposal->technicalNeeds()->orderBy('sort_order')->pluck('budget_amount_cents')->all())->toBe([700, 300])
        ->and($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::ProposalAdjusted);
});

test('stale confirmed sources roll back cuts and adjustments', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'first' => $first, 'files' => $files] = cutFixture();
    $fingerprint = app(OwnRevenueCutReconciliation::class)->forProposal($proposal)['fingerprint'];
    OwnRevenueImportFile::factory()->for($files['abpre']->session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'detected_format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_by' => $manager->id,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $fingerprint,
        'cuts' => [[
            'target_type' => 'technical', 'target_id' => $first->id, 'stable_key' => 'technical:a',
            'specific_item_code' => '21101', 'amount_cents' => '100',
        ]],
    ])->assertSessionHasErrors('reconciliation_fingerprint');

    expect(OwnRevenueProposalCut::query()->count())->toBe(0)
        ->and(OwnRevenueProposal::query()->count())->toBe(1)
        ->and($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::ProposalCalculated);
});

test('cuts workspace exposes operational totals and permissions', function () {
    $this->withoutVite();
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager] = cutFixture();

    $this->actingAs($manager)->get(route('finance.own-revenue.budgets.proposals.cuts.show', [$budget, $proposal]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/planning/cuts')
            ->where('summary.required_cut_cents', '400')
            ->has('candidates', 2)
            ->where('permissions.manage', true));

    $auditor = cutUser(UserRole::FinanceAuditor);
    $this->actingAs($auditor)->get(route('finance.own-revenue.budgets.proposals.cuts.show', [$budget, $proposal]))
        ->assertInertia(fn (Assert $page) => $page->where('permissions.manage', false));
    $this->actingAs($auditor)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => str_repeat('a', 64), 'cuts' => [],
    ])->assertForbidden();
});
