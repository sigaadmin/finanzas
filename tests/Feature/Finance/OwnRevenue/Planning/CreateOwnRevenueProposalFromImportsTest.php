<?php

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueProposalFingerprint;
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
    foreach (['26101' => 'Combustibles', '37101' => 'Pasajes aéreos', '37501' => 'Viáticos'] as $code => $name) {
        ExpenseClassification::query()->create([
            'fiscal_year' => $budget->fiscal_year,
            'chapter_code' => '3000',
            'chapter_name' => 'Servicios generales',
            'concept_code' => '3700',
            'concept_name' => 'Servicios de traslado y viáticos',
            'generic_item_code' => substr($code, 0, 4).'0',
            'generic_item_name' => $name,
            'specific_item_code' => $code,
            'specific_item_name' => $name,
            'expense_type_code' => '1',
            'expense_type_name' => 'Gasto corriente',
        ]);
    }
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
        ->create(['amount_cents' => 79_300]);
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

test('materialized calculated fields are current and retain an imported fuel override for audit', function () {
    ['budget' => $budget, 'manager' => $manager, 'files' => $files] = planningProposalFixture();
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);

    $this->actingAs($manager)->post(
        route('finance.own-revenue.budgets.proposals.from-imports.store', $budget),
        planningProposalPayload($files, $readiness->fingerprint),
    )->assertSessionHasNoErrors();

    $proposal = OwnRevenueProposal::query()->sole();
    $fuelNeed = $proposal->fuelNeeds()->sole();

    expect($fuelNeed->total_kilometers)->toBe('300.0000')
        ->and($fuelNeed->liters)->toBe('30.0000')
        ->and($fuelNeed->mathematical_amount_cents)->toBe(72_090)
        ->and($fuelNeed->rounded_amount_cents)->toBe(72_100)
        ->and($fuelNeed->budget_amount_cents)->toBe(79_300)
        ->and($fuelNeed->rounding_difference_cents)->toBe(2_910)
        ->and($fuelNeed->override_justification)->toBe('Importe conservado del archivo confirmado.');

    $commission = $proposal->travelCommissions()->sole();
    expect($commission->participants)->each(fn ($participant) => $participant
        ->per_diem_amount_cents->toBe(234_620)
        ->lodging_amount_cents->toBe(93_848)
        ->total_amount_cents->toBe(328_468))
        ->and($commission->participants_amount_cents)->toBe(656_936)
        ->and($commission->total_amount_cents)->toBe(656_936);

    $this->actingAs($manager)->post(
        route('finance.own-revenue.budgets.proposals.calculate', [$budget, $proposal]),
        ['proposal_fingerprint' => app(OwnRevenueProposalFingerprint::class)->forProposal($proposal)],
    )->assertSessionHasNoErrors();

    expect($proposal->fresh()->status)->toBe(OwnRevenueProposalStatus::Calculated);
});

test('work sheet concepts without a detailed format become auditable planning needs', function () {
    ['budget' => $budget, 'manager' => $manager, 'activity' => $activity, 'files' => $files] = planningProposalFixture();
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios generales',
        'concept_code' => '3900',
        'concept_name' => 'Otros servicios generales',
        'generic_item_code' => '39900',
        'generic_item_name' => 'Otros servicios generales',
        'specific_item_code' => '39904',
        'specific_item_name' => 'Otros servicios generales',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $line = OwnRevenueWorkSheetLine::factory()
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($classification, 'expenseClassification')
        ->create([
            'own_revenue_import_file_id' => $files[OwnRevenueImportFormat::WorkSheet->value]->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'item_name' => $classification->specific_item_name,
            'specific_item_code' => $classification->specific_item_code,
            'annual_amount_cents' => 100_000,
        ]);
    $line->months()->create(['month' => 6, 'amount_cents' => 100_000]);
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);

    $this->actingAs($manager)->post(
        route('finance.own-revenue.budgets.proposals.from-imports.store', $budget),
        planningProposalPayload($files, $readiness->fingerprint),
    )->assertSessionHasNoErrors();

    $residual = OwnRevenueProposal::query()->sole()->technicalNeeds()
        ->where('stable_key', 'work-sheet:'.$line->id.':06')
        ->sole();

    expect($residual->source_technical_sheet_need_id)->toBeNull()
        ->and($residual->specific_item_code)->toBe('39904')
        ->and($residual->description)->toBe('Concepto incorporado desde la Hoja de trabajo: Otros servicios generales')
        ->and($residual->quantity)->toBe('1.0000')
        ->and($residual->unit_price_cents)->toBe(100_000)
        ->and($residual->reference_amount_cents)->toBe(100_000)
        ->and($residual->budget_amount_cents)->toBe(100_000)
        ->and($residual->budget_month)->toBe(6);
});

test('legacy work sheet travel totals are split into food and lodging using the ABPRE', function () {
    ['budget' => $budget, 'manager' => $manager, 'activity' => $activity, 'files' => $files] = planningProposalFixture();
    $food = ExpenseClassification::query()->where('fiscal_year', $budget->fiscal_year)
        ->where('specific_item_code', '37501')->sole();
    $lodging = ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios generales',
        'concept_code' => '3700',
        'concept_name' => 'Servicios de traslado y viáticos',
        'generic_item_code' => '37500',
        'generic_item_name' => 'Viáticos',
        'specific_item_code' => '37502',
        'specific_item_name' => 'Gastos de hospedaje',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $workSheetLine = OwnRevenueWorkSheetLine::factory()
        ->for($budget, 'budget')->for($activity, 'activity')->for($food, 'expenseClassification')
        ->create([
            'own_revenue_import_file_id' => $files['work_sheet']->id,
            'activity_code' => $activity->code,
            'activity_name' => $activity->name,
            'item_name' => $food->specific_item_name,
            'specific_item_code' => '37501',
            'annual_amount_cents' => 300_000,
        ]);
    $workSheetLine->months()->create(['month' => 7, 'amount_cents' => 300_000]);
    foreach ([[$food, 200_000], [$lodging, 100_000]] as [$classification, $amount]) {
        $abpreLine = OwnRevenueAbpreLine::factory()
            ->for($budget, 'budget')->for($classification, 'expenseClassification')
            ->create([
                'own_revenue_import_file_id' => $files['abpre']->id,
                'specific_item_code' => $classification->specific_item_code,
                'annual_amount_cents' => $amount,
            ]);
        $abpreLine->months()->create(['month' => 7, 'amount_cents' => $amount]);
    }
    $readiness = app(OwnRevenueProposalReadiness::class)->forBudget($budget);

    $this->actingAs($manager)->post(
        route('finance.own-revenue.budgets.proposals.from-imports.store', $budget),
        planningProposalPayload($files, $readiness->fingerprint),
    )->assertSessionHasNoErrors();

    $residuals = OwnRevenueProposal::query()->sole()->technicalNeeds()
        ->whereIn('stable_key', [
            'work-sheet:'.$workSheetLine->id.':07',
            'work-sheet:'.$workSheetLine->id.':07:lodging',
        ])
        ->orderBy('specific_item_code')
        ->get();

    expect($residuals)->toHaveCount(2)
        ->and($residuals->pluck('specific_item_code')->all())->toBe(['37501', '37502'])
        ->and($residuals->pluck('budget_amount_cents')->all())->toBe([200_000, 100_000]);
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
