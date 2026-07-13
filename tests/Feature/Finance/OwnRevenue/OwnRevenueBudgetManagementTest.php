<?php

use App\Actions\Finance\OwnRevenue\InitializeOwnRevenueBudget;
use App\Actions\Finance\OwnRevenue\UpdateOwnRevenueBudgetSettings;
use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\UserRole;
use App\Http\Requests\Finance\OwnRevenue\StoreOwnRevenueBudgetRequest;
use App\Http\Requests\Finance\OwnRevenue\UpdateOwnRevenueBudgetRequest;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function ownRevenueHttpUser(UserRole $role = UserRole::FinanceManager, bool $active = true): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => $active,
    ]);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function ownRevenueHttpBudgetData(array $overrides = []): array
{
    return array_replace([
        'fiscal_year' => 2027,
        'institution_name' => 'Centro Regional de Educación Normal',
        'responsible_unit_code' => '2112102003',
        'responsible_unit_name' => 'Dirección del Plantel',
        'budget_program_code' => 'E062',
        'budget_program_name' => 'Formación Inicial Docente',
        'component_code' => 'C01',
        'component_name' => 'Servicios de formación docente proporcionados',
        'official_activity_code' => 'A01',
        'official_activity_name' => 'Operación de los programas de formación docente',
        'estimated_income_cents' => 1_250_000,
        'cut_percentage' => '5.25',
        'uma_value' => '113.14',
        'uma_status' => AnnualValueStatus::Final->value,
        'fuel_price_per_liter' => '24.5',
        'fuel_price_status' => AnnualValueStatus::Provisional->value,
    ], $overrides);
}

function ownRevenueHttpSource(User $creator, int $fiscalYear = 2026): OwnRevenueBudget
{
    $budget = app(InitializeOwnRevenueBudget::class)->handle(
        $creator,
        ownRevenueHttpBudgetData(['fiscal_year' => $fiscalYear]),
    );

    $budget->signatories()->create([
        'role_key' => 'authorized_by',
        'name' => 'Directora de la institución',
        'position' => 'Directora',
        'academic_degree' => 'Dra.',
        'sort_order' => 1,
    ]);

    ExpenseClassification::query()->create([
        'fiscal_year' => $fiscalYear,
        'chapter_code' => '3000',
        'chapter_name' => 'SERVICIOS GENERALES',
        'concept_code' => '3700',
        'concept_name' => 'SERVICIOS DE TRASLADO Y VIÁTICOS',
        'generic_item_code' => '3750',
        'generic_item_name' => 'VIÁTICOS EN EL PAÍS',
        'specific_item_code' => '37501',
        'specific_item_name' => 'VIÁTICOS EN EL PAÍS',
        'expense_type_code' => '1',
        'expense_type_name' => 'GASTO CORRIENTE',
    ]);

    return $budget;
}

test('annual budget routes use the required names methods and static create ordering', function () {
    expect(route('finance.own-revenue.budgets.index', absolute: false))->toBe('/finance/own-revenue/budgets')
        ->and(route('finance.own-revenue.budgets.create', absolute: false))->toBe('/finance/own-revenue/budgets/create')
        ->and(route('finance.own-revenue.budgets.store', absolute: false))->toBe('/finance/own-revenue/budgets')
        ->and(route('finance.own-revenue.budgets.show', 123, absolute: false))->toBe('/finance/own-revenue/budgets/123')
        ->and(route('finance.own-revenue.budgets.update', 123, absolute: false))->toBe('/finance/own-revenue/budgets/123')
        ->and(route('finance.own-revenue.budgets.cog.confirm', 123, absolute: false))->toBe('/finance/own-revenue/budgets/123/cog/confirm')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.index')?->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.create')?->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.store')?->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.show')?->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.update')?->methods())->toBe(['PUT'])
        ->and(Route::getRoutes()->getByName('finance.own-revenue.budgets.cog.confirm')?->methods())->toBe(['POST']);
});

test('index returns descending explicit annual budget DTOs and create permission', function () {
    $manager = ownRevenueHttpUser();
    OwnRevenueBudget::factory()->create(['fiscal_year' => 2026, 'institution_name' => 'No debe filtrarse']);
    $latest = OwnRevenueBudget::factory()->create(['fiscal_year' => 2028]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/index', false)
            ->has('budgets', 2)
            ->where('budgets.0.id', $latest->id)
            ->where('budgets.0.fiscal_year', 2028)
            ->where('budgets.0.status', 'draft')
            ->where('budgets.0.region.code', '02-001')
            ->where('budgets.0.uma.status', 'final')
            ->where('budgets.0.fuel.status', 'provisional')
            ->where('budgets.0.cog.status', 'pending_confirmation')
            ->where('permissions.create', true)
            ->missing('budgets.0.institution_name')
            ->missing('budgets.0.created_by'));
});

test('create returns only prior source budget DTOs and permission', function () {
    $manager = ownRevenueHttpUser();
    OwnRevenueBudget::factory()->create(['fiscal_year' => 2028, 'institution_name' => 'Dato sensible']);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/create', false)
            ->has('sourceBudgets', 1)
            ->where('sourceBudgets.0.fiscal_year', 2028)
            ->where('sourceBudgets.0.status', 'draft')
            ->where('permissions.create', true)
            ->missing('sourceBudgets.0.institution_name'));
});

test('manager can initialize a blank annual budget from validated institutional data', function () {
    $manager = ownRevenueHttpUser();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), ownRevenueHttpBudgetData())
        ->assertRedirect(route('finance.own-revenue.budgets.show', 1))
        ->assertInertiaFlash('success', 'Presupuesto anual de ingresos propios creado correctamente.');

    $budget = OwnRevenueBudget::query()->sole();

    expect($budget->created_by)->toBe($manager->id)
        ->and($budget->fiscal_year)->toBe(2027)
        ->and($budget->activities()->count())->toBe(4)
        ->and($budget->uma_value)->toBe('113.1400');
});

test('manager can copy a prior annual budget using only source and destination year', function () {
    $manager = ownRevenueHttpUser();
    $source = ownRevenueHttpSource($manager, 2026);

    $response = $this->actingAs($manager)->post(route('finance.own-revenue.budgets.store'), [
        'source_budget_id' => $source->id,
        'fiscal_year' => 2027,
    ]);

    $destination = OwnRevenueBudget::query()->where('fiscal_year', 2027)->sole();

    $response->assertRedirect(route('finance.own-revenue.budgets.show', $destination))
        ->assertInertiaFlash('success', 'Presupuesto anual de ingresos propios copiado correctamente.');

    expect($destination->created_by)->toBe($manager->id)
        ->and($destination->institution_name)->toBe($source->institution_name)
        ->and($destination->signatories()->value('role_key'))->toBe('authorized_by')
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(1);
});

test('copy mode rejects institutional fields so destination year is the only editable destination input', function () {
    $manager = ownRevenueHttpUser();
    $source = ownRevenueHttpSource($manager, 2026);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), [
            'source_budget_id' => $source->id,
            'fiscal_year' => 2027,
            'institution_name' => 'Intento de sobrescritura',
        ])
        ->assertSessionHasErrors('institution_name');

    expect(OwnRevenueBudget::query()->where('fiscal_year', 2027)->exists())->toBeFalse();
});

test('show exposes ordered explicit settings activities signatories cog audit and permissions', function () {
    $manager = ownRevenueHttpUser();
    $budget = ownRevenueHttpSource($manager, 2026);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/show', false)
            ->where('budget.id', $budget->id)
            ->where('budget.fiscal_year', 2026)
            ->where('budget.settings.uma_value', '113.1400')
            ->where('budget.settings.fuel_price_per_liter', '24.5000')
            ->where('budget.activities.0.code', 'A01')
            ->where('budget.signatories.0.role_key', 'authorized_by')
            ->where('budget.cog.row_count', 1)
            ->where('budget.cog.source_year', null)
            ->where('budget.cog.status', 'pending_confirmation')
            ->where('budget.cog.confirmed_by', null)
            ->where('budget.cog.confirmed_at', null)
            ->where('permissions.updateSettings', true)
            ->where('permissions.copy', true)
            ->where('permissions.confirmCog', true)
            ->missing('budget.created_by'));
});

test('settings and nested signatories are updated atomically', function () {
    $manager = ownRevenueHttpUser();
    $budget = ownRevenueHttpSource($manager);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), [
            'institution_name' => 'Institución actualizada',
            'uma_value' => '120.5',
            'uma_status' => 'final',
            'signatories' => [
                [
                    'role_key' => 'prepared_by',
                    'name' => 'Responsable Financiera',
                    'position' => 'Jefa de Recursos Financieros',
                    'academic_degree' => null,
                    'sort_order' => 1,
                ],
                [
                    'role_key' => 'authorized_by',
                    'name' => 'Directora Actualizada',
                    'position' => 'Directora',
                    'academic_degree' => 'Dra.',
                    'sort_order' => 2,
                ],
            ],
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.show', $budget))
        ->assertInertiaFlash('success', 'Configuración del presupuesto actualizada correctamente.');

    expect($budget->refresh()->institution_name)->toBe('Institución actualizada')
        ->and($budget->uma_value)->toBe('120.5000')
        ->and($budget->signatories()->orderBy('sort_order')->pluck('role_key')->all())
        ->toBe(['prepared_by', 'authorized_by']);
});

test('an empty signatories array clears signatories intentionally', function () {
    $manager = ownRevenueHttpUser();
    $budget = ownRevenueHttpSource($manager);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), ['signatories' => []])
        ->assertRedirect(route('finance.own-revenue.budgets.show', $budget));

    expect($budget->signatories()->count())->toBe(0);
});

test('omitted signatories leave the existing signatories unchanged', function () {
    $manager = ownRevenueHttpUser();
    $budget = ownRevenueHttpSource($manager);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), ['institution_name' => 'Sólo configuración'])
        ->assertRedirect(route('finance.own-revenue.budgets.show', $budget));

    expect($budget->signatories()->sole()->role_key)->toBe('authorized_by');
});

test('failed signatory persistence rolls back settings and existing signatories', function () {
    $budget = ownRevenueHttpSource(ownRevenueHttpUser());
    $originalName = $budget->institution_name;

    OwnRevenueSignatory::creating(function (): never {
        throw new RuntimeException('No se pudo guardar el firmante.');
    });

    try {
        app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, [
            'institution_name' => 'No debe persistir',
            'signatories' => [
                ['role_key' => 'prepared_by', 'name' => 'Responsable', 'position' => 'Cargo', 'sort_order' => 1],
            ],
        ]);
    } catch (Throwable $exception) {
    } finally {
        OwnRevenueSignatory::flushEventListeners();
    }

    expect($exception ?? null)->toBeInstanceOf(RuntimeException::class)
        ->and($budget->refresh()->institution_name)->toBe($originalName)
        ->and($budget->signatories()->sole()->role_key)->toBe('authorized_by');
});

test('manager confirms COG with the authenticated persisted user', function () {
    $manager = ownRevenueHttpUser();
    $budget = ownRevenueHttpSource($manager);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.cog.confirm', $budget))
        ->assertRedirect(route('finance.own-revenue.budgets.show', $budget))
        ->assertInertiaFlash('success', 'Catálogo COG confirmado correctamente.');

    expect($budget->refresh()->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($budget->cog_confirmed_by)->toBe($manager->id)
        ->and($budget->cog_confirmed_at)->not->toBeNull();
});

test('read only finance roles can index and show but cannot administrate budgets', function (UserRole $role) {
    $user = ownRevenueHttpUser($role);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2026]);

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('permissions.create', false));
    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('permissions.updateSettings', false)
            ->where('permissions.copy', false)
            ->where('permissions.confirmCog', false));
    $this->actingAs($user)->get(route('finance.own-revenue.budgets.create'))->assertForbidden();
    $this->actingAs($user)->post(route('finance.own-revenue.budgets.store'), ownRevenueHttpBudgetData())->assertForbidden();
    $this->actingAs($user)->put(route('finance.own-revenue.budgets.update', $budget), ['institution_name' => 'No'])->assertForbidden();
    $this->actingAs($user)->post(route('finance.own-revenue.budgets.cog.confirm', $budget))->assertForbidden();
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);

test('users outside finance are blocked by the existing middleware', function (string $state) {
    $user = match ($state) {
        'public' => ownRevenueHttpUser(UserRole::Public),
        'inactive' => ownRevenueHttpUser(UserRole::FinanceManager, false),
        default => User::factory()->create(),
    };

    $this->actingAs($user)
        ->get('/finance/own-revenue/budgets')
        ->assertForbidden();
})->with(['public', 'inactive', 'missing access']);

test('guests are redirected by the existing authentication middleware', function () {
    $this->get('/finance/own-revenue/budgets')->assertRedirect(route('login'));
});

test('store validates a unique four digit fiscal year', function (array $payload) {
    $manager = ownRevenueHttpUser();
    OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), ownRevenueHttpBudgetData($payload))
        ->assertSessionHasErrors('fiscal_year');
})->with([
    'duplicate' => [['fiscal_year' => 2027]],
    'three digits' => [['fiscal_year' => 999]],
    'five digits' => [['fiscal_year' => 10000]],
]);

test('copy source must exist and be earlier than destination', function (array $payload, string $field) {
    $manager = ownRevenueHttpUser();
    $source = ownRevenueHttpSource($manager, 2027);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), array_replace([
            'source_budget_id' => $source->id,
            'fiscal_year' => 2028,
        ], $payload))
        ->assertSessionHasErrors($field);
})->with([
    'missing source' => [['source_budget_id' => 999999], 'source_budget_id'],
    'same source year' => [['fiscal_year' => 2027], 'source_budget_id'],
    'destination before source' => [['fiscal_year' => 2026], 'source_budget_id'],
]);

test('store rejects invalid decimal strings estimated cents and cut percentage', function (string $field, mixed $value) {
    $manager = ownRevenueHttpUser();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), ownRevenueHttpBudgetData([
            'fiscal_year' => 2030,
            $field => $value,
        ]))
        ->assertSessionHasErrors($field);
})->with([
    'zero UMA' => ['uma_value', '0'],
    'negative fuel' => ['fuel_price_per_liter', '-1'],
    'too many decimals' => ['uma_value', '1.23456'],
    'too many fuel decimals' => ['fuel_price_per_liter', '24.12345'],
    'numeric not string' => ['fuel_price_per_liter', 24.5],
    'negative cents' => ['estimated_income_cents', -1],
    'cut over one hundred' => ['cut_percentage', '100.01'],
    'cut numeric not string' => ['cut_percentage', 5],
]);

test('annual value requests declare the explicit zero to four decimal scale rule', function () {
    $storeRules = (new StoreOwnRevenueBudgetRequest)->rules();
    $updateRules = (new UpdateOwnRevenueBudgetRequest)->rules();

    expect($storeRules['uma_value'])->toContain('decimal:0,4')
        ->and($storeRules['fuel_price_per_liter'])->toContain('decimal:0,4')
        ->and($updateRules['uma_value'])->toContain('decimal:0,4')
        ->and($updateRules['fuel_price_per_liter'])->toContain('decimal:0,4');
});

test('store accepts positive annual decimal strings with zero to four decimal places', function (string $value) {
    $manager = ownRevenueHttpUser();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.store'), ownRevenueHttpBudgetData([
            'fiscal_year' => 2032,
            'uma_value' => $value,
            'fuel_price_per_liter' => $value,
        ]))
        ->assertRedirect();

    expect(OwnRevenueBudget::query()->sole()->uma_value)->toBe(number_format((float) $value, 4, '.', ''))
        ->and(OwnRevenueBudget::query()->sole()->fuel_price_per_liter)->toBe(number_format((float) $value, 4, '.', ''));
})->with([
    'zero decimals' => '1',
    'one decimal' => '1.2',
    'four decimals' => '1.2345',
]);

test('update accepts positive annual decimal strings with zero to four decimal places', function (string $value) {
    $manager = ownRevenueHttpUser();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2033]);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), [
            'uma_value' => $value,
            'fuel_price_per_liter' => $value,
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.show', $budget));

    expect($budget->refresh()->uma_value)->toBe(number_format((float) $value, 4, '.', ''))
        ->and($budget->fuel_price_per_liter)->toBe(number_format((float) $value, 4, '.', ''));
})->with([
    'zero decimals' => '1',
    'one decimal' => '1.2',
    'four decimals' => '1.2345',
]);

test('update rejects annual decimal strings with five decimal places', function (string $field) {
    $manager = ownRevenueHttpUser();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2034]);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), [$field => '1.23456'])
        ->assertSessionHasErrors($field);
})->with(['uma_value', 'fuel_price_per_liter']);

test('nested signatories validate count fields lengths ordering and distinct roles', function (array $signatories, string $field) {
    $manager = ownRevenueHttpUser();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2031]);

    $this->actingAs($manager)
        ->put(route('finance.own-revenue.budgets.update', $budget), ['signatories' => $signatories])
        ->assertSessionHasErrors($field);
})->with([
    'duplicate roles' => [[
        ['role_key' => 'prepared_by', 'name' => 'Uno', 'position' => 'Cargo', 'sort_order' => 1],
        ['role_key' => 'prepared_by', 'name' => 'Dos', 'position' => 'Cargo', 'sort_order' => 2],
    ], 'signatories.1.role_key'],
    'missing name' => [[
        ['role_key' => 'prepared_by', 'position' => 'Cargo', 'sort_order' => 1],
    ], 'signatories.0.name'],
    'invalid sort order' => [[
        ['role_key' => 'prepared_by', 'name' => 'Uno', 'position' => 'Cargo', 'sort_order' => 0],
    ], 'signatories.0.sort_order'],
    'role too long' => [[
        ['role_key' => str_repeat('a', 101), 'name' => 'Uno', 'position' => 'Cargo', 'sort_order' => 1],
    ], 'signatories.0.role_key'],
    'too many signatories' => [array_map(
        fn (int $index): array => [
            'role_key' => "role_{$index}",
            'name' => "Firmante {$index}",
            'position' => 'Cargo',
            'sort_order' => $index + 1,
        ],
        range(0, 10),
    ), 'signatories'],
]);
