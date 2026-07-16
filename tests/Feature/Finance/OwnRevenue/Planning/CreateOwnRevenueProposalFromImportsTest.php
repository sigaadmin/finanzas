<?php

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalReadiness;

function planningProposalManager(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'planning-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, manager: User, activity: OwnRevenueActivity, files: array<string, OwnRevenueImportFile>, sources: array<int, OwnRevenueTechnicalSheetNeed|OwnRevenueFuelPlan|OwnRevenueTravelCommission>} */
function planningProposalFixture(): array
{
    $manager = planningProposalManager();
    $budget = OwnRevenueBudget::factory()->create([
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => $manager->id,
        'cog_confirmed_at' => now(),
        'uma_status' => AnnualValueStatus::Final,
        'fuel_price_status' => AnnualValueStatus::Final,
    ]);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'prepared_by', 'sort_order' => 1]);
    OwnRevenueSignatory::factory()->for($budget, 'budget')->create(['role_key' => 'authorized_by', 'sort_order' => 2]);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales de oficina',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->for($manager, 'createdBy')->create();
    $files = collect(OwnRevenueImportFormat::cases())->mapWithKeys(function (OwnRevenueImportFormat $format) use ($budget, $manager, $session): array {
        $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
            'own_revenue_budget_id' => $budget->id,
            'uploaded_by' => $manager->id,
            'format' => $format,
            'detected_format' => $format,
            'detected_year' => $budget->fiscal_year,
            'status' => OwnRevenueImportFileStatus::Confirmed,
            'confirmed_by' => $manager->id,
            'confirmed_at' => now(),
        ]);

        return [$format->value => $file];
    })->all();

    $technical = OwnRevenueTechnicalSheetNeed::factory()
        ->for($files[OwnRevenueImportFormat::TechnicalSheet->value], 'file')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($classification, 'expenseClassification')
        ->create(['amount_cents' => 25_063]);
    $fuel = OwnRevenueFuelPlan::factory()
        ->for($files[OwnRevenueImportFormat::Fuel->value], 'file')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['amount_cents' => 75_000]);
    $travelA = OwnRevenueTravelCommission::factory()
        ->for($files[OwnRevenueImportFormat::TravelExpenses->value], 'file')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['reason' => 'Supervisión', 'person_name' => 'Persona uno']);
    $travelB = OwnRevenueTravelCommission::factory()
        ->for($files[OwnRevenueImportFormat::TravelExpenses->value], 'file')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create([
            'commission_date_label' => $travelA->commission_date_label,
            'month' => $travelA->month,
            'reason' => $travelA->reason,
            'destination' => $travelA->destination,
            'person_name' => 'Persona dos',
        ]);

    return [
        'budget' => $budget,
        'manager' => $manager,
        'activity' => $activity,
        'files' => $files,
        'sources' => [$technical, $fuel, $travelA, $travelB],
    ];
}

/** @param array<string, OwnRevenueImportFile> $files @return array<string, int|string> */
function planningProposalPayload(array $files, string $fingerprint): array
{
    return [
        'source_abpre_file_id' => $files[OwnRevenueImportFormat::Abpre->value]->id,
        'source_work_sheet_file_id' => $files[OwnRevenueImportFormat::WorkSheet->value]->id,
        'source_technical_sheet_file_id' => $files[OwnRevenueImportFormat::TechnicalSheet->value]->id,
        'source_fuel_file_id' => $files[OwnRevenueImportFormat::Fuel->value]->id,
        'source_travel_expenses_file_id' => $files[OwnRevenueImportFormat::TravelExpenses->value]->id,
        'source_fingerprint' => $fingerprint,
    ];
}

test('confirmed imports materialize one idempotent editable proposal without mutating evidence', function () {
    ['budget' => $budget, 'manager' => $manager, 'files' => $files, 'sources' => $sources] = planningProposalFixture();
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);
    $sourceSnapshots = collect($sources)->mapWithKeys(fn ($source): array => [
        $source::class.'|'.$source->id => collect($source->getAttributes())->sortKeys()->all(),
    ]);
    $payload = planningProposalPayload($files, $readiness->fingerprint);
    $route = route('finance.own-revenue.budgets.proposals.from-imports.store', $budget);

    $this->actingAs($manager)->post($route, $payload)
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('success', 'Propuesta creada desde las importaciones confirmadas.');
    $this->actingAs($manager)->post($route, $payload)->assertSessionHasNoErrors();

    $proposal = OwnRevenueProposal::query()->sole();
    expect($readiness->ready)->toBeTrue()
        ->and($proposal->status)->toBe(OwnRevenueProposalStatus::Draft)
        ->and($proposal->source_fingerprint)->toHaveLength(64)
        ->and($proposal->technicalNeeds)->toHaveCount(1)
        ->and($proposal->fuelNeeds)->toHaveCount(1)
        ->and($proposal->travelCommissions)->toHaveCount(1)
        ->and($proposal->travelCommissions->sole()->participants)->toHaveCount(2)
        ->and(OwnRevenueProposal::query()->count())->toBe(1);

    foreach ($sources as $source) {
        expect(collect($source->fresh()->getAttributes())->sortKeys()->all())
            ->toBe($sourceSnapshots[$source::class.'|'.$source->id]);
    }
});

test('a stale import observation creates no partial proposal', function () {
    ['budget' => $budget, 'manager' => $manager, 'files' => $files] = planningProposalFixture();
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);
    $session = $files[OwnRevenueImportFormat::Fuel->value]->session;
    OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => OwnRevenueImportFormat::Fuel,
        'detected_format' => OwnRevenueImportFormat::Fuel,
        'version_number' => 2,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_by' => $manager->id,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.proposals.from-imports.store', $budget), planningProposalPayload($files, $readiness->fingerprint))
        ->assertSessionHasErrors('source_fingerprint');

    expect(OwnRevenueProposal::query()->count())->toBe(0);
});

test('readiness reports user facing blockers', function (string $scenario, string $message) {
    ['budget' => $budget, 'files' => $files, 'sources' => $sources] = planningProposalFixture();

    match ($scenario) {
        'missing format' => $files[OwnRevenueImportFormat::Abpre->value]->delete(),
        'pending activity' => $sources[0]->update(['own_revenue_activity_id' => null]),
        'import issue' => $files[OwnRevenueImportFormat::Abpre->value]->issues()->create([
            'severity' => 'error', 'code' => 'test.error', 'message' => 'Dato inválido.',
        ]),
        'cog' => $budget->update(['cog_status' => CogCatalogStatus::PendingConfirmation]),
        'annual value' => $budget->update(['uma_status' => AnnualValueStatus::PendingReview]),
        'signatory' => $budget->signatories()->delete(),
    };

    $result = app(OwnRevenueProposalReadiness::class)->forBudget($budget->fresh());

    expect($result->ready)->toBeFalse()
        ->and($result->blockers)->toContain($message);
})->with([
    'missing format' => ['missing format', 'Falta confirmar el archivo ABPRE.'],
    'pending activity' => ['pending activity', 'Hay registros complementarios sin actividad asignada.'],
    'import issue' => ['import issue', 'Las importaciones confirmadas todavía contienen incidencias de error.'],
    'COG' => ['cog', 'Confirma el catálogo COG antes de crear la propuesta.'],
    'annual value' => ['annual value', 'Revisa y confirma los valores anuales de UMA y combustible.'],
    'signatory' => ['signatory', 'Registra las personas que elaboran y autorizan la planeación.'],
]);

test('read only finance roles cannot create proposals', function (UserRole $role) {
    ['budget' => $budget, 'files' => $files] = planningProposalFixture();
    $assistant = planningProposalManager($role);
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);

    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.proposals.from-imports.store', $budget), planningProposalPayload($files, $readiness->fingerprint))
        ->assertForbidden();
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);
