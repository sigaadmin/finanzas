<?php

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Http\Requests\Finance\OwnRevenue\Planning\StoreOwnRevenueProposalCutsRequest;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;
use App\Services\Finance\OwnRevenue\Planning\ProportionalCutSuggestion;
use Illuminate\Support\Facades\Validator;
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
        'specific_item_code' => '21101',
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

test('ABPRE totals are allocated across work sheet activities when both confirmed formats differ', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'files' => $files] = cutFixture();
    $classification = $proposal->technicalNeeds()->firstOrFail()->expenseClassification;
    $secondActivity = OwnRevenueActivity::factory()->for($budget, 'budget')->create([
        'code' => 'A02',
        'name' => 'Actividad dos',
    ]);
    $secondLine = OwnRevenueWorkSheetLine::factory()
        ->for($budget, 'budget')
        ->for($secondActivity, 'activity')
        ->for($classification, 'expenseClassification')
        ->create([
            'own_revenue_import_file_id' => $files['work_sheet']->id,
            'activity_code' => $secondActivity->code,
            'activity_name' => $secondActivity->name,
            'specific_item_code' => '21101',
            'annual_amount_cents' => 400,
        ]);
    $secondLine->months()->create(['month' => 5, 'amount_cents' => 400]);
    $proposal->technicalNeeds()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_activity_id' => $secondActivity->id,
        'expense_classification_id' => $classification->id,
        'stable_key' => 'technical:c',
        'specific_item_code' => '21101',
        'specific_item_name' => $classification->specific_item_name,
        'chapter_code' => $classification->chapter_code,
        'chapter_name' => $classification->chapter_name,
        'quantity' => '1.0000',
        'unit' => 'LOTE',
        'description' => 'Concepto de la actividad dos',
        'unit_price_cents' => 400,
        'reference_amount_cents' => 400,
        'budget_amount_cents' => 400,
        'budget_month' => 5,
        'region_code' => '02-001',
        'region_name' => 'Felipe Carrillo Puerto',
    ]);
    $abpreLine = $files['abpre']->abpreLines()->sole();
    $abpreLine->update(['annual_amount_cents' => 700]);
    $abpreLine->months()->sole()->update(['amount_cents' => 700]);
    $proposal->update(['total_amount_cents' => 1_400]);

    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());
    $groups = collect($reconciliation['groups'])->keyBy('activity_code');

    expect($reconciliation['ready'])->toBeTrue()
        ->and($reconciliation['blockers'])->toBe([])
        ->and($groups[$activity->code]['target_amount_cents'])->toBe('420')
        ->and($groups[$activity->code]['required_cut_cents'])->toBe('580')
        ->and($groups[$secondActivity->code]['target_amount_cents'])->toBe('280')
        ->and($groups[$secondActivity->code]['required_cut_cents'])->toBe('120')
        ->and($reconciliation['summary']['abpre_amount_cents'])->toBe('700')
        ->and($reconciliation['summary']['required_cut_cents'])->toBe('700');
});

test('a favorable ABPRE difference is reported as an automatic reconciliation increase', function () {
    ['proposal' => $proposal, 'files' => $files] = cutFixture();
    $abpreLine = $files['abpre']->abpreLines()->sole();
    $abpreLine->update(['annual_amount_cents' => 1_200]);
    $abpreLine->months()->sole()->update(['amount_cents' => 1_200]);

    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());

    expect($reconciliation['ready'])->toBeTrue()
        ->and($reconciliation['blockers'])->toBe([])
        ->and($reconciliation['groups'][0])->toMatchArray([
            'required_cut_cents' => '0',
            'required_increase_cents' => '200',
            'pending_cut_cents' => '0',
        ])
        ->and($reconciliation['summary'])->toMatchArray([
            'calculated_amount_cents' => '1000',
            'abpre_amount_cents' => '1200',
            'required_cut_cents' => '0',
            'required_increase_cents' => '200',
            'pending_cut_cents' => '0',
            'adjusted_amount_cents' => '1200',
        ]);
});

test('mixed ABPRE differences keep reductions and increases separate', function () {
    [
        'budget' => $budget,
        'proposal' => $proposal,
        'manager' => $manager,
        'activity' => $activity,
        'first' => $first,
        'second' => $second,
        'files' => $files,
    ] = cutFixture();
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000', 'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100', 'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21200', 'generic_item_name' => 'Materiales de impresión',
        'specific_item_code' => '21201', 'specific_item_name' => 'Materiales de impresión',
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
    $workSheetLine = OwnRevenueWorkSheetLine::factory()
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($classification, 'expenseClassification')
        ->create([
            'own_revenue_import_file_id' => $files['work_sheet']->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'specific_item_code' => '21201',
            'annual_amount_cents' => 300,
        ]);
    $workSheetLine->months()->create(['month' => 5, 'amount_cents' => 300]);
    $abpreLine = OwnRevenueAbpreLine::factory()
        ->for($budget, 'budget')
        ->for($classification, 'expenseClassification')
        ->create([
            'own_revenue_import_file_id' => $files['abpre']->id,
            'specific_item_code' => '21201',
            'annual_amount_cents' => 300,
        ]);
    $abpreLine->months()->create(['month' => 5, 'amount_cents' => 300]);
    OwnRevenueProposalTechnicalNeed::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($classification, 'expenseClassification')
        ->create([
            'stable_key' => 'technical:mixed-increase',
            'specific_item_code' => '21201',
            'specific_item_name' => 'Materiales de impresión',
            'budget_amount_cents' => 100,
            'reference_amount_cents' => 100,
            'unit_price_cents' => 100,
            'quantity' => '1.0000',
            'budget_month' => 5,
            'sort_order' => 3,
        ]);
    $proposal->update(['total_amount_cents' => 1_100]);

    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());
    $groups = collect($reconciliation['groups'])->keyBy('specific_item_code');

    expect($reconciliation['ready'])->toBeTrue()
        ->and($groups['21101']['required_cut_cents'])->toBe('400')
        ->and($groups['21101']['required_increase_cents'])->toBe('0')
        ->and($groups['21201']['required_cut_cents'])->toBe('0')
        ->and($groups['21201']['required_increase_cents'])->toBe('200')
        ->and($reconciliation['summary'])->toMatchArray([
            'calculated_amount_cents' => '1100',
            'abpre_amount_cents' => '900',
            'required_cut_cents' => '400',
            'required_increase_cents' => '200',
            'adjusted_amount_cents' => '1300',
        ]);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $reconciliation['fingerprint'],
        'cuts' => [
            ['target_type' => 'technical', 'target_id' => $first->id, 'stable_key' => 'technical:a', 'specific_item_code' => '21101', 'amount_cents' => '280'],
            ['target_type' => 'technical', 'target_id' => $second->id, 'stable_key' => 'technical:b', 'specific_item_code' => '21101', 'amount_cents' => '120'],
        ],
    ])->assertSessionHasNoErrors();
    $current = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());

    expect($current['summary']['adjusted_amount_cents'])->toBe('900');

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.adjust', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $current['fingerprint'],
    ])->assertSessionHasNoErrors();

    $adjusted = OwnRevenueProposal::query()->where('version_number', 2)->sole();
    $adjustedReconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($adjusted);

    expect($adjusted->total_amount_cents)->toBe(900)
        ->and($adjusted->technicalNeeds()->where('stable_key', 'like', 'abpre-adjustment:%')->value('budget_amount_cents'))->toBe(200)
        ->and($adjustedReconciliation['summary'])->toMatchArray([
            'calculated_amount_cents' => '900',
            'abpre_amount_cents' => '900',
            'required_cut_cents' => '0',
            'required_increase_cents' => '0',
        ]);
});

test('cut validation accepts separate food and lodging targets', function () {
    $payload = [
        'reconciliation_fingerprint' => str_repeat('a', 64),
        'cuts' => [
            [
                'target_type' => 'travel_per_diem',
                'target_id' => 1,
                'stable_key' => 'travel:1:per-diem',
                'specific_item_code' => '37501',
                'amount_cents' => '100',
            ],
            [
                'target_type' => 'travel_lodging',
                'target_id' => 1,
                'stable_key' => 'travel:1:lodging',
                'specific_item_code' => '37502',
                'amount_cents' => '50',
            ],
        ],
    ];

    $validator = Validator::make($payload, (new StoreOwnRevenueProposalCutsRequest)->rules());

    expect($validator->passes())->toBeTrue();
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

test('a favorable ABPRE difference creates an independent reconciliation line without changing source needs', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'files' => $files] = cutFixture();
    $abpreLine = $files['abpre']->abpreLines()->sole();
    $abpreLine->update(['annual_amount_cents' => 1_200]);
    $abpreLine->months()->sole()->update(['amount_cents' => 1_200]);
    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh());

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.adjust', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $reconciliation['fingerprint'],
    ])->assertSessionHasNoErrors();

    $adjusted = OwnRevenueProposal::query()->where('version_number', 2)->sole();
    $adjustment = $adjusted->technicalNeeds()->where('stable_key', 'like', 'abpre-adjustment:%')->sole();

    expect($adjusted->total_amount_cents)->toBe(1_200)
        ->and($adjusted->technicalNeeds()->count())->toBe(3)
        ->and($adjustment)->toMatchArray([
            'specific_item_code' => '21101',
            'quantity' => '1.0000',
            'unit' => 'AJUSTE',
            'description' => 'Ajuste de conciliación con ABPRE',
            'budget_amount_cents' => 200,
            'budget_month' => 5,
            'region_code' => '02-001',
        ])
        ->and($proposal->technicalNeeds()->orderBy('sort_order')->pluck('budget_amount_cents')->all())->toBe([700, 300]);
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

test('an adjusted proposal can be authorized into an immutable initial budget snapshot', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'first' => $first, 'second' => $second] = cutFixture();
    $reconciliation = app(OwnRevenueCutReconciliation::class)->forProposal($proposal);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.cuts.store', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $reconciliation['fingerprint'],
        'cuts' => [
            ['target_type' => 'technical', 'target_id' => $first->id, 'stable_key' => 'technical:a', 'specific_item_code' => '21101', 'amount_cents' => '280'],
            ['target_type' => 'technical', 'target_id' => $second->id, 'stable_key' => 'technical:b', 'specific_item_code' => '21101', 'amount_cents' => '120'],
        ],
    ])->assertSessionHasNoErrors();
    $fingerprint = app(OwnRevenueCutReconciliation::class)->forProposal($proposal->fresh())['fingerprint'];
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.adjust', [$budget, $proposal]), [
        'reconciliation_fingerprint' => $fingerprint,
    ])->assertSessionHasNoErrors();
    $adjusted = OwnRevenueProposal::query()->where('status', OwnRevenueProposalStatus::Adjusted)->sole();

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.initial-authorization.store', [$budget, $adjusted]), [
        'authorization_fingerprint' => app(OwnRevenueCutReconciliation::class)->forProposal($adjusted)['fingerprint'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('own_revenue_initial_budgets', [
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_proposal_id' => $adjusted->id,
        'total_amount_cents' => 600,
        'authorized_by' => $manager->id,
    ]);
    $snapshot = OwnRevenueInitialBudget::query()->sole()->snapshot;
    expect($snapshot['technical_needs'])->toHaveCount(2)
        ->and($snapshot['technical_needs'][0])->toMatchArray([
            'activity' => 'A01', 'activity_name' => 'Actividad uno', 'item' => '21101',
            'item_name' => 'Materiales y útiles de oficina', 'quantity' => '1.0000',
            'unit_price_cents' => '700', 'month' => 5, 'amount_cents' => '420',
            'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
        ]);
    expect($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::InitialAuthorized);
});
