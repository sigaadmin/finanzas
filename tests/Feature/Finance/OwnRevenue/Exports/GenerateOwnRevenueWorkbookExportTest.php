<?php

use App\Actions\Finance\OwnRevenue\Exports\GenerateOwnRevenueWorkbookExport;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

function workbookExportUser(UserRole $role): User
{
    $email = sprintf('%s-export-%s@crenfcp.edu.mx', $role->value, fake()->uuid());
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{0: OwnRevenueBudget, 1: OwnRevenueInitialBudget} */
function authorizedInitialBudget(): array
{
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InitialAuthorized,
        'fiscal_year' => 2026,
    ]);
    $proposal = OwnRevenueProposal::factory()->create([
        'own_revenue_budget_id' => $budget,
        'total_amount_cents' => 125000,
    ]);
    $initialBudget = OwnRevenueInitialBudget::query()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_proposal_id' => $proposal->id,
        'total_amount_cents' => 125000,
        'source_fingerprint' => str_repeat('a', 64),
        'authorization_fingerprint' => str_repeat('b', 64),
        'snapshot' => [
            'budget' => ['fiscal_year' => 2026],
            'reconciliation' => ['groups' => [[
                'specific_item_code' => '21103',
                'month' => 4,
                'target_amount_cents' => '125000',
            ]]],
            'technical_needs' => [],
            'fuel_needs' => [],
            'travel_commissions' => [],
        ],
        'authorized_by' => $budget->created_by,
        'authorized_at' => now(),
    ]);

    return [$budget, $initialBudget];
}

test('an administrator generates a private auditable workbook from the authorized snapshot', function () {
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();
    $user = workbookExportUser(UserRole::FinanceManager);

    $export = app(GenerateOwnRevenueWorkbookExport::class)->handle($budget, $initialBudget, $user, 'abpre');

    Storage::disk('local')->assertExists($export->storage_path);
    $contents = Storage::disk('local')->get($export->storage_path);
    $path = tempnam(sys_get_temp_dir(), 'generated-abpre');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);
    expect($export->format)->toBe('abpre')
        ->and($export->storage_path)->toStartWith('own-revenue/exports/')
        ->and($export->sha256)->toBe(hash('sha256', $contents))
        ->and($export->getRawOriginal('total_amount_cents'))->toBe(125000)
        ->and($export->generated_by)->toBe($user->id)
        ->and($sheet->getCell('A7')->getValue())->toBe((int) $budget->responsible_unit_code)
        ->and($sheet->getCell('A7')->getDataType())->toBe('n');
});

test('the generated ABPRE justification sheet uses the budget COG descriptions', function () {
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();
    ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración, emisión de documentos y artículos oficiales',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales, útiles y equipos menores de oficina',
        'specific_item_code' => '21103',
        'specific_item_name' => 'Papelería',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $export = app(GenerateOwnRevenueWorkbookExport::class)->handle(
        $budget,
        $initialBudget,
        workbookExportUser(UserRole::FinanceManager),
        'abpre',
    );
    $path = tempnam(sys_get_temp_dir(), 'generated-abpre-justification');
    file_put_contents($path, Storage::disk('local')->get($export->storage_path));
    $sheet = IOFactory::load($path)->getSheetByName('Formato Justificación Partidas');
    unlink($path);

    expect($sheet)->not->toBeNull()
        ->and($sheet->getCell('C7')->getValue())->toBe(2000)
        ->and($sheet->getCell('D7')->getValue())->toBe('Materiales y suministros')
        ->and($sheet->getCell('F7')->getValue())->toBe('Papelería');
});

test('generation rejects budgets that are not authorized', function () {
    [$budget, $initialBudget] = authorizedInitialBudget();
    $budget->update(['status' => OwnRevenueBudgetStatus::ProposalAdjusted]);

    app(GenerateOwnRevenueWorkbookExport::class)->handle(
        $budget->fresh(),
        $initialBudget,
        workbookExportUser(UserRole::FinanceManager),
        'abpre',
    );
})->throws(AuthorizationException::class);

test('generation rejects read only users and unknown formats', function (string $format, UserRole $role, string $exception) {
    [$budget, $initialBudget] = authorizedInitialBudget();

    expect(fn () => app(GenerateOwnRevenueWorkbookExport::class)->handle(
        $budget,
        $initialBudget,
        workbookExportUser($role),
        $format,
    ))->toThrow($exception);
})->with([
    'auditor' => ['abpre', UserRole::FinanceAuditor, AuthorizationException::class],
    'unknown format' => ['calendar', UserRole::FinanceManager, ValidationException::class],
]);

test('the registered workbook can be downloaded by a finance user', function () {
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();
    $manager = workbookExportUser(UserRole::FinanceManager);
    $export = app(GenerateOwnRevenueWorkbookExport::class)->handle($budget, $initialBudget, $manager, 'abpre');

    $this->actingAs(workbookExportUser(UserRole::FinanceAuditor))
        ->get(route('finance.own-revenue.workbook-exports.download', $export))
        ->assertSuccessful()
        ->assertDownload($export->file_name);
});

test('the generation endpoint validates and returns to planning', function () {
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();

    $this->actingAs(workbookExportUser(UserRole::FinanceManager))
        ->post(route('finance.own-revenue.budgets.workbook-exports.store', [$budget, $initialBudget]), [
            'format' => 'technical_sheet',
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertSessionHasNoErrors();

    expect($initialBudget->workbookExports()->where('format', 'technical_sheet')->exists())->toBeTrue();
});

test('planning exposes the authorized budget export history and generation permission', function () {
    $this->withoutVite();
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();
    $manager = workbookExportUser(UserRole::FinanceManager);
    $export = app(GenerateOwnRevenueWorkbookExport::class)->handle($budget, $initialBudget, $manager, 'abpre');

    $this->actingAs($manager)->get(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertInertia(fn (Assert $page) => $page
            ->where('initial_budget.id', $initialBudget->id)
            ->where('initial_budget.exports.0.id', $export->id)
            ->where('initial_budget.exports.0.format', 'abpre')
            ->where('permissions.generate_exports', true));
});

test('older authorized snapshots recover descriptive fields without changing authorized amounts', function () {
    Storage::fake('local');
    [$budget, $initialBudget] = authorizedInitialBudget();
    $proposal = $initialBudget->proposal;
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create(['code' => 'A01', 'name' => 'Operación']);
    OwnRevenueProposalTechnicalNeed::factory()->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')->create([
        'stable_key' => 'legacy-technical', 'description' => 'Papel institucional', 'budget_amount_cents' => 999999,
    ]);
    $snapshot = $initialBudget->snapshot;
    $snapshot['technical_needs'] = [[
        'stable_key' => 'legacy-technical', 'activity' => 'A01', 'item' => '21101', 'month' => 4, 'amount_cents' => '125000',
    ]];
    $initialBudget->update(['snapshot' => $snapshot]);

    $export = app(GenerateOwnRevenueWorkbookExport::class)->handle(
        $budget,
        $initialBudget->fresh(),
        workbookExportUser(UserRole::FinanceManager),
        'technical_sheet',
    );
    $path = tempnam(sys_get_temp_dir(), 'legacy-export');
    file_put_contents($path, Storage::disk('local')->get($export->storage_path));
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getCell('E17')->getValue())->toBe('Papel institucional')
        ->and($sheet->getCell('I17')->getValue())->toBe(1250);
});
