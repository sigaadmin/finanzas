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
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalFingerprint;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalProjector;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;
use Inertia\Testing\AssertableInertia as Assert;

function calculationUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'proposal-calculation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function calculationClassification(int $year, string $code): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $year,
        'chapter_code' => substr($code, 0, 1).'000', 'chapter_name' => 'Capítulo '.$code,
        'concept_code' => substr($code, 0, 2).'00', 'concept_name' => 'Concepto '.$code,
        'generic_item_code' => substr($code, 0, 4).'0', 'generic_item_name' => 'Partida genérica '.$code,
        'specific_item_code' => $code, 'specific_item_name' => 'Partida '.$code,
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
}

/** @return array{budget: OwnRevenueBudget, proposal: OwnRevenueProposal, manager: User, activity: OwnRevenueActivity, technical: OwnRevenueProposalTechnicalNeed, fuel: OwnRevenueProposalFuelNeed, commission: OwnRevenueProposalTravelCommission, participant: OwnRevenueProposalTravelParticipant} */
function calculationFixture(): array
{
    $manager = calculationUser();
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::Draft,
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => $manager->id,
        'cog_confirmed_at' => now(),
        'uma_value' => '117.3100', 'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '24.5000', 'fuel_price_status' => AnnualValueStatus::Final,
        'fuel_budget_month' => 4,
    ]);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'prepared_by']);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'authorized_by']);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A01', 'name' => 'Actividad uno']);
    $technicalClassification = calculationClassification($budget->fiscal_year, '21101');
    calculationClassification($budget->fiscal_year, '26101');
    calculationClassification($budget->fiscal_year, '37501');
    calculationClassification($budget->fiscal_year, '37101');
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->for($manager, 'createdBy')->create();
    $files = collect(OwnRevenueImportFormat::cases())->mapWithKeys(function (OwnRevenueImportFormat $format) use ($budget, $manager, $session): array {
        $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
            'own_revenue_budget_id' => $budget->id, 'uploaded_by' => $manager->id,
            'format' => $format, 'detected_format' => $format,
            'status' => OwnRevenueImportFileStatus::Confirmed,
            'confirmed_by' => $manager->id, 'confirmed_at' => now(),
        ]);

        return [$format->value => $file];
    });
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create([
        'version_number' => 1,
        'status' => OwnRevenueProposalStatus::Draft,
        'source_abpre_file_id' => $files['abpre']->id,
        'source_work_sheet_file_id' => $files['work_sheet']->id,
        'source_technical_sheet_file_id' => $files['technical_sheet']->id,
        'source_fuel_file_id' => $files['fuel']->id,
        'source_travel_expenses_file_id' => $files['travel_expenses']->id,
        'source_fingerprint' => $readiness->fingerprint,
        'total_amount_cents' => 383_468,
    ]);
    $technical = OwnRevenueProposalTechnicalNeed::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')
        ->for($technicalClassification, 'expenseClassification')->create([
            'quantity' => '2.0000', 'unit_price_cents' => 10_000,
            'reference_amount_cents' => 20_000, 'budget_amount_cents' => 20_000,
            'budget_month' => 5, 'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
        ]);
    $route = OwnRevenueRoute::factory()->for($budget, 'budget')->create([
        'one_way_kilometers' => '50', 'additional_kilometers' => '0',
    ]);
    $fuel = OwnRevenueProposalFuelNeed::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')->for($route, 'route')->create([
            'kilometers_per_liter' => '10',
            'outbound_kilometers' => '50', 'return_kilometers' => '50', 'additional_kilometers' => '0',
            'total_kilometers' => '100', 'liters' => '10', 'fuel_price' => '24.5',
            'mathematical_amount_cents' => 24_500, 'rounded_amount_cents' => 24_500,
            'budget_amount_cents' => 25_000, 'rounding_difference_cents' => 500,
            'operational_month' => 8, 'budget_month' => 4,
        ]);
    $destination = OwnRevenueTravelDestination::factory()->for($budget, 'budget')->create();
    $rate = OwnRevenueTravelRate::factory()->for($budget, 'budget')->create([
        'position' => 'Docente', 'normalized_position' => 'docente',
        'per_diem_uma' => '10', 'lodging_uma' => '8',
    ]);
    $commission = OwnRevenueProposalTravelCommission::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')
        ->for($destination, 'travelDestination')->create([
            'uma_value' => '117.31', 'flight_amount_cents' => 10_000,
            'participants_amount_cents' => 328_468, 'total_amount_cents' => 338_468,
            'operational_month' => 8, 'budget_month' => 8,
        ]);
    $participant = OwnRevenueProposalTravelParticipant::factory()->for($commission, 'commission')->for($rate, 'travelRate')->create([
        'own_revenue_proposal_id' => $proposal->id,
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_activity_id' => $activity->id,
        'commission_days' => '2', 'per_diem_uma' => '10', 'lodging_uma' => '8',
        'per_diem_amount_cents' => 234_620, 'lodging_amount_cents' => 93_848, 'total_amount_cents' => 328_468,
    ]);

    return compact('budget', 'proposal', 'manager', 'activity', 'technical', 'fuel', 'commission', 'participant');
}

test('projector creates canonical work sheet and abpre summaries with exact totals', function () {
    ['proposal' => $proposal] = calculationFixture();

    $projection = app(OwnRevenueProposalProjector::class)->project($proposal);

    expect($projection['work_sheet'])->toHaveCount(4)
        ->and(array_column($projection['work_sheet'], 'specific_item_code'))->toBe(['21101', '26101', '37101', '37501'])
        ->and(array_column($projection['work_sheet'], 'month'))->toBe([5, 4, 8, 8])
        ->and(array_column($projection['work_sheet'], 'amount_cents'))->toBe(['20000', '25000', '10000', '328468'])
        ->and(array_unique(array_column($projection['work_sheet'], 'region_code')))->toBe(['02-001'])
        ->and($projection['abpre'])->toHaveCount(4)
        ->and($projection['total_amount_cents'])->toBe('383468');
});

test('a current valid draft becomes an immutable calculated proposal', function () {
    $this->withoutVite();
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'technical' => $technical] = calculationFixture();
    $fingerprint = app(OwnRevenueProposalFingerprint::class)->forProposal($proposal);

    $this->actingAs($manager)->get(route('finance.own-revenue.budgets.planning.show', [
        'budget' => $budget,
        'proposal_version' => 1,
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('proposal.fingerprint', $fingerprint)
        ->where('permissions.calculate', true)
        ->where('permissions.revise', false));

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.calculate', [$budget, $proposal]), [
        'proposal_fingerprint' => $fingerprint,
    ])->assertSessionHasNoErrors();

    expect($proposal->fresh()->status)->toBe(OwnRevenueProposalStatus::Calculated)
        ->and($proposal->fresh()->calculated_by)->toBe($manager->id)
        ->and($proposal->fresh()->calculated_at)->not->toBeNull()
        ->and($proposal->fresh()->source_fingerprint)->toHaveLength(64)
        ->and($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::ProposalCalculated);

    $this->actingAs($manager)->get(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.calculate', false)
            ->where('permissions.revise', true));

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.proposals.technical-needs.update', [$budget, $proposal, $technical]), [
        'own_revenue_activity_id' => $technical->own_revenue_activity_id,
    ])->assertForbidden();
});

test('stale or invalid proposal calculations roll back completely', function (string $scenario, string $error) {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager, 'technical' => $technical, 'fuel' => $fuel] = calculationFixture();
    $fingerprint = app(OwnRevenueProposalFingerprint::class)->forProposal($proposal);

    match ($scenario) {
        'stale' => $technical->update(['budget_amount_cents' => 20_001]),
        'region' => $technical->update(['region_code' => '01-001']),
        'calculator' => $fuel->update(['liters' => '11']),
    };
    if ($scenario !== 'stale') {
        $fingerprint = app(OwnRevenueProposalFingerprint::class)->forProposal($proposal->fresh());
    }

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.calculate', [$budget, $proposal]), [
        'proposal_fingerprint' => $fingerprint,
    ])->assertSessionHasErrors($error);

    expect($proposal->fresh()->status)->toBe(OwnRevenueProposalStatus::Draft)
        ->and($proposal->fresh()->calculated_at)->toBeNull()
        ->and($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::Draft);
})->with([
    'stale observation' => ['stale', 'proposal_fingerprint'],
    'invalid region' => ['region', 'proposal'],
    'invalid calculator snapshot' => ['calculator', 'proposal'],
]);

test('a calculated version can create one independent editable revision', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'manager' => $manager] = calculationFixture();
    $fingerprint = app(OwnRevenueProposalFingerprint::class)->forProposal($proposal);
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.calculate', [$budget, $proposal]), [
        'proposal_fingerprint' => $fingerprint,
    ])->assertSessionHasNoErrors();

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.revisions.store', [$budget, $proposal]))
        ->assertSessionHasNoErrors();

    $revision = OwnRevenueProposal::query()->where('version_number', 2)->sole();
    expect($revision->status)->toBe(OwnRevenueProposalStatus::Draft)
        ->and($revision->based_on_proposal_id)->toBe($proposal->id)
        ->and($revision->technicalNeeds)->toHaveCount(1)
        ->and($revision->fuelNeeds)->toHaveCount(1)
        ->and($revision->travelCommissions)->toHaveCount(1)
        ->and($revision->travelCommissions->sole()->participants)->toHaveCount(1)
        ->and($proposal->fresh()->status)->toBe(OwnRevenueProposalStatus::Calculated)
        ->and($budget->fresh()->status)->toBe(OwnRevenueBudgetStatus::Draft);

    $revision->technicalNeeds()->firstOrFail()->update(['budget_amount_cents' => 99_999]);
    expect($proposal->technicalNeeds()->firstOrFail()->budget_amount_cents)->toBe(20_000);
});

test('assistants and auditors cannot calculate or revise proposals', function (UserRole $role) {
    ['budget' => $budget, 'proposal' => $proposal] = calculationFixture();
    $user = calculationUser($role);

    $this->actingAs($user)->post(route('finance.own-revenue.budgets.proposals.calculate', [$budget, $proposal]), [
        'proposal_fingerprint' => app(OwnRevenueProposalFingerprint::class)->forProposal($proposal),
    ])->assertForbidden();
})->with([
    'assistant' => UserRole::FinanceAssistant,
    'auditor' => UserRole::FinanceAuditor,
]);
