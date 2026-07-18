<?php

use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpenseRequirementException;
use App\Actions\Finance\OwnRevenue\Execution\CompleteExpenseRequirement;
use App\Actions\Finance\OwnRevenue\Execution\CreateExpenseRequirementRule;
use App\Actions\Finance\OwnRevenue\Execution\DeactivateExpenseRequirementRule;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpenseSufficiency;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExecutionViewData;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function requirementOperator(UserRole $role = UserRole::FinanceAssistant): User
{
    $email = 'requirement-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

test('only active rules whose conditions match are materialized for a dossier stage', function () {
    $operator = requirementOperator();
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'requested_by' => $operator,
        'amount_cents' => 20_000,
        'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren,
    ]);
    $matching = OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren,
        'chapter_code' => $dossier->budgetLine->chapter_code,
        'specific_item_code' => $dossier->budgetLine->specific_item_code,
        'minimum_amount_cents' => 15_000,
    ]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'minimum_amount_cents' => 25_000,
    ]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'is_active' => false,
    ]);

    $requirements = app(OwnRevenueExpenseRequirements::class)->syncForStage(
        $dossier,
        OwnRevenueExpenseDossierStatus::SufficiencyRequested,
    );

    expect($requirements)->toHaveCount(1)
        ->and($requirements->sole()->own_revenue_expense_requirement_rule_id)->toBe($matching->id)
        ->and($requirements->sole()->status)->toBe(OwnRevenueExpenseRequirementStatus::Pending);
});

test('an applicable pending requirement blocks its transition until it is completed', function () {
    $operator = requirementOperator();
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'requested_by' => $operator,
        'amount_cents' => 4_000,
    ]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'title' => 'Solicitud firmada',
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
    ]);

    expect(fn () => app(RequestExpenseSufficiency::class)->handle($dossier, $operator))
        ->toThrow(ValidationException::class, 'Solicitud firmada');

    $requirement = $dossier->requirements()->sole();
    $requirement->update([
        'status' => OwnRevenueExpenseRequirementStatus::Completed,
        'notes' => 'Documento revisado.',
        'acted_by' => $operator->id,
        'acted_at' => now(),
    ]);

    app(RequestExpenseSufficiency::class)->handle($dossier, $operator);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::SufficiencyRequested);
});

test('an operator completes a requirement with its required private evidence', function () {
    Storage::fake('local');
    $operator = requirementOperator();
    $dossier = OwnRevenueExpenseDossier::factory()->create(['requested_by' => $operator]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'requires_evidence' => true,
    ]);
    $requirement = app(OwnRevenueExpenseRequirements::class)
        ->syncForStage($dossier, OwnRevenueExpenseDossierStatus::SufficiencyRequested)
        ->sole();

    expect(fn () => app(CompleteExpenseRequirement::class)->handle(
        $requirement,
        $operator,
        'La evidencia fue revisada.',
        null,
    ))->toThrow(ValidationException::class);

    app(CompleteExpenseRequirement::class)->handle(
        $requirement,
        $operator,
        'La evidencia fue revisada.',
        UploadedFile::fake()->create('solicitud.pdf', 20, 'application/pdf'),
    );

    $requirement->refresh();
    expect($requirement->status)->toBe(OwnRevenueExpenseRequirementStatus::Completed)
        ->and($requirement->evidence_document_id)->not->toBeNull()
        ->and($requirement->actor->is($operator))->toBeTrue();
    Storage::disk('local')->assertExists($requirement->evidenceDocument->storage_path);
});

test('only an administrator can authorize a documented requirement exception', function () {
    Storage::fake('local');
    $operator = requirementOperator();
    $manager = requirementOperator(UserRole::FinanceManager);
    $dossier = OwnRevenueExpenseDossier::factory()->create(['requested_by' => $operator]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
    ]);
    $requirement = app(OwnRevenueExpenseRequirements::class)
        ->syncForStage($dossier, OwnRevenueExpenseDossierStatus::SufficiencyRequested)
        ->sole();
    $evidence = fn () => UploadedFile::fake()->create('excepcion.pdf', 20, 'application/pdf');

    expect(fn () => app(AuthorizeExpenseRequirementException::class)->handle(
        $requirement,
        $operator,
        'Se autoriza por situación extraordinaria documentada.',
        $evidence(),
    ))->toThrow(AuthorizationException::class);

    app(AuthorizeExpenseRequirementException::class)->handle(
        $requirement,
        $manager,
        'Se autoriza por situación extraordinaria documentada.',
        $evidence(),
    );

    expect($requirement->fresh()->status)->toBe(OwnRevenueExpenseRequirementStatus::Excepted)
        ->and($requirement->fresh()->exception_evidence_document_id)->not->toBeNull();
});

test('the execution workspace exposes requirements and accepts their completion endpoint', function () {
    Storage::fake('local');
    $operator = requirementOperator();
    $dossier = OwnRevenueExpenseDossier::factory()->create(['requested_by' => $operator]);
    OwnRevenueExpenseRequirementRule::factory()->create([
        'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
        'title' => 'Factura firmada',
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'requires_evidence' => true,
    ]);
    $requirement = app(OwnRevenueExpenseRequirements::class)
        ->syncForStage($dossier, OwnRevenueExpenseDossierStatus::SufficiencyRequested)
        ->sole();

    $viewData = $this->actingAs($operator)
        ->app->make(OwnRevenueExecutionViewData::class)
        ->forBudget($dossier->budget);
    expect($viewData['expense_dossiers'][0]['requirements'][0])
        ->toMatchArray([
            'id' => $requirement->id,
            'title' => 'Factura firmada',
            'status' => 'pending',
            'requires_evidence' => true,
        ])
        ->and($viewData['permissions']['complete_expense_requirement'])->toBeTrue();

    $this->post(route('finance.own-revenue.budgets.execution.expense-dossiers.requirements.complete', [
        $dossier->budget,
        $dossier,
        $requirement,
    ]), [
        'notes' => 'Documento revisado.',
        'evidence' => UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'),
    ])->assertRedirect(route('finance.own-revenue.budgets.execution.show', $dossier->budget));

    expect($requirement->fresh()->status)->toBe(OwnRevenueExpenseRequirementStatus::Completed);
});

test('execution workspace source includes requirement controls without opening another window', function () {
    $source = file_get_contents(resource_path('js/pages/finance/own-revenue/execution/show.tsx'));

    expect($source)
        ->toContain('expenseDossierRoutes.requirements.complete')
        ->toContain('expenseDossierRoutes.requirements.except')
        ->toContain('Requisitos del expediente')
        ->not->toContain('target="_blank"');
});

test('an administrator configures and deactivates a conditional requirement for existing dossiers', function () {
    $assistant = requirementOperator();
    $manager = requirementOperator(UserRole::FinanceManager);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'requested_by' => $assistant,
        'amount_cents' => 20_000,
        'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren,
    ]);
    $data = [
        'title' => 'Tres cotizaciones firmadas',
        'description' => 'Aplica a compras mayores a $15,000.',
        'target_status' => OwnRevenueExpenseDossierStatus::PurchaseInProgress->value,
        'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren->value,
        'chapter_code' => $dossier->budgetLine->chapter_code,
        'specific_item_code' => null,
        'minimum_amount_cents' => 15_000,
        'requires_evidence' => true,
    ];

    expect(fn () => app(CreateExpenseRequirementRule::class)->handle($dossier->budget, $assistant, $data))
        ->toThrow(AuthorizationException::class);

    $rule = app(CreateExpenseRequirementRule::class)->handle($dossier->budget, $manager, $data);

    expect($rule->is_active)->toBeTrue()
        ->and($dossier->requirements()->whereBelongsTo($rule, 'rule')->count())->toBe(1);

    app(DeactivateExpenseRequirementRule::class)->handle($rule, $manager);

    expect($rule->fresh()->is_active)->toBeFalse()
        ->and($dossier->requirements()->whereBelongsTo($rule, 'rule')->count())->toBe(0);
});

test('requirement rule endpoints are restricted to administrators', function () {
    $assistant = requirementOperator();
    $manager = requirementOperator(UserRole::FinanceManager);
    $dossier = OwnRevenueExpenseDossier::factory()->create(['requested_by' => $assistant]);
    $payload = [
        'title' => 'Factura y XML',
        'description' => 'Documentos fiscales de la compra.',
        'target_status' => OwnRevenueExpenseDossierStatus::PaymentRequested->value,
        'purchase_responsibility' => null,
        'chapter_code' => null,
        'specific_item_code' => null,
        'minimum_amount_cents' => null,
        'requires_evidence' => true,
    ];
    $route = route('finance.own-revenue.budgets.execution.requirement-rules.store', $dossier->budget);

    $this->actingAs($assistant)->post($route, $payload)->assertForbidden();
    $this->actingAs($manager)->post($route, $payload)
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $dossier->budget));
    $rule = OwnRevenueExpenseRequirementRule::query()->where('title', 'Factura y XML')->sole();

    $this->delete(route('finance.own-revenue.budgets.execution.requirement-rules.deactivate', [
        $dossier->budget,
        $rule,
    ]))->assertRedirect(route('finance.own-revenue.budgets.execution.show', $dossier->budget));

    expect($rule->fresh()->is_active)->toBeFalse();
});

test('a newly configured rule does not apply retroactively to a stage already reached', function () {
    $manager = requirementOperator(UserRole::FinanceManager);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'requested_by' => $manager,
        'status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
        'sufficiency_requested_at' => now(),
    ]);

    app(CreateExpenseRequirementRule::class)->handle($dossier->budget, $manager, [
        'title' => 'Solicitud firmada',
        'target_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested->value,
        'requires_evidence' => true,
    ]);

    expect($dossier->requirements()->count())->toBe(0);
});
