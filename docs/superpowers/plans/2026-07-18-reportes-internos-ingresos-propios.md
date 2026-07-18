# Reportes internos de Ingresos Propios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir un tablero de sólo lectura para consultar saldos, modificaciones, expedientes, avance presupuestal y fondo de combustible de un ejercicio de Ingresos Propios.

**Architecture:** Un servicio de lectura compone todos los datos desde las tablas actuales y reutiliza `OwnRevenueBudgetBalance` para mantener una sola definición de saldos. Un resumen de combustible compartido sustituye los valores provisionales del espacio de combustible. Un controlador invocable autoriza la consulta y entrega a Inertia un contrato con importes como cadenas.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Laravel/React 3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

## Estructura de archivos

- Crear `app/Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData.php`: normaliza filtros y compone el contrato completo del tablero.
- Crear `app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelSummary.php`: calcula adquisición, consumo confirmado, necesidades pendientes y disponibilidad.
- Modificar `app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelViewData.php`: reutiliza el resumen real de combustible.
- Crear `app/Http/Controllers/Finance/OwnRevenueInternalReportController.php`: autoriza y renderiza el tablero.
- Modificar `routes/web.php`: registra la ruta GET con nombre `finance.own-revenue.budgets.reports.show`.
- Crear `resources/js/pages/finance/own-revenue/reports/show.tsx`: presenta filtros, saldos y resúmenes de sólo lectura.
- Modificar `resources/js/pages/finance/own-revenue/budgets/show.tsx`: agrega el acceso al tablero.
- Crear `tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php`: cubre cálculos, filtros, aislamiento y precisión.
- Crear `tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest.php`: cubre ruta, permisos y contrato Inertia.
- Crear `tests/Frontend/own-revenue-internal-reports.test.mjs`: cubre lenguaje, navegación y tratamiento de importes.

### Task 1: Calcular el resumen real del fondo de combustible

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelSummary.php`
- Modify: `app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelViewData.php`
- Test: `tests/Feature/Finance/OwnRevenue/Fuel/OwnRevenueFuelFundNavigationTest.php`

- [ ] **Step 1: Escribir la prueba fallida**

Agregar una prueba que abra un fondo de `100000`, cree una comisión pendiente de `30000` y una confirmada de `25000`, y afirme:

```php
$this->actingAs($manager)
    ->get(route('finance.own-revenue.budgets.fuel.show', $budget))
    ->assertSuccessful()
    ->assertInertia(fn (Assert $page) => $page
        ->where('summary.acquired_amount_cents', '100000')
        ->where('summary.confirmed_consumption_cents', '25000')
        ->where('summary.pending_needs_cents', '30000')
        ->where('summary.available_amount_cents', '75000'));
```

- [ ] **Step 2: Verificar RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Fuel/OwnRevenueFuelFundNavigationTest.php --filter="real commission balances"`

Expected: FAIL porque `confirmed_consumption_cents` y `pending_needs_cents` todavía son `0`.

- [ ] **Step 3: Implementar el servicio mínimo**

Crear `OwnRevenueFuelSummary` con este contrato:

```php
final class OwnRevenueFuelSummary
{
    /** @return array{acquired_amount_cents:string,confirmed_consumption_cents:string,pending_needs_cents:string,available_amount_cents:string} */
    public function forBudget(OwnRevenueBudget $budget): array
    {
        $fund = $budget->fuelFund()->first();
        $acquired = (int) ($fund?->getRawOriginal('acquired_amount_cents') ?? 0);
        $confirmed = $fund === null ? 0 : (int) $fund->commissions()
            ->where('status', OwnRevenueFuelCommissionStatus::Confirmed)->sum('amount_cents');
        $pending = $fund === null ? 0 : (int) $fund->commissions()
            ->where('status', OwnRevenueFuelCommissionStatus::Pending)->sum('amount_cents');

        return [
            'acquired_amount_cents' => (string) $acquired,
            'confirmed_consumption_cents' => (string) $confirmed,
            'pending_needs_cents' => (string) $pending,
            'available_amount_cents' => (string) ($acquired - $confirmed),
        ];
    }
}
```

Inyectarlo en `OwnRevenueFuelViewData` y reemplazar el arreglo provisional por `$this->summary->forBudget($budget)`.

- [ ] **Step 4: Verificar GREEN y formato**

Run:

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Fuel/OwnRevenueFuelFundNavigationTest.php tests/Feature/Finance/OwnRevenue/Fuel/ManageOwnRevenueFuelCommissionsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelSummary.php app/Services/Finance/OwnRevenue/Fuel/OwnRevenueFuelViewData.php tests/Feature/Finance/OwnRevenue/Fuel/OwnRevenueFuelFundNavigationTest.php
git commit -m "fix: report operational fuel balances"
```

### Task 2: Construir el contrato presupuestal filtrable

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData.php`
- Create: `tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php`

- [ ] **Step 1: Generar servicio y prueba**

```bash
php artisan make:class Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest --no-interaction
```

- [ ] **Step 2: Escribir pruebas fallidas de saldos e aislamiento**

Crear un ejercicio con líneas en dos capítulos y meses, modificaciones y expedientes en estados `sufficiency_requested`, `sufficiency_confirmed`, `paid`, `cancelled` y `rejected`. Invocar:

```php
$data = app(OwnRevenueInternalReportData::class)->forBudget($budget, []);

expect($data['summary'])->toMatchArray([
    'initial_amount_cents' => '100000',
    'modified_amount_cents' => '100000',
    'reserved_amount_cents' => '10000',
    'committed_amount_cents' => '20000',
    'paid_amount_cents' => '30000',
    'available_amount_cents' => '40000',
])->and($data['planning_vs_execution'])->toMatchArray([
    'planned_amount_cents' => '100000',
    'paid_amount_cents' => '30000',
    'difference_amount_cents' => '70000',
    'execution_percentage' => '30.00',
]);
```

Agregar otro presupuesto con datos testigo y afirmar que no altera resultados. Agregar un importe `9007199254740993` y afirmar que regresa como cadena exacta.

- [ ] **Step 3: Verificar RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php`

Expected: FAIL porque la clase no contiene `forBudget`.

- [ ] **Step 4: Implementar resumen y renglones**

Implementar:

```php
final class OwnRevenueInternalReportData
{
    public function __construct(
        private readonly OwnRevenueBudgetBalance $balances,
        private readonly OwnRevenueFuelSummary $fuelSummary,
    ) {}

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function forBudget(OwnRevenueBudget $budget, array $input): array
    {
        $allLines = $budget->modifiedBudgetLines()
            ->withSum('incomingModifications', 'amount_cents')
            ->withSum('outgoingModifications', 'amount_cents')
            ->orderBy('chapter_code')->orderBy('specific_item_code')->orderBy('month')->get();
        $filters = $this->normalizeFilters($allLines, $input);
        $lines = $allLines->filter(fn (OwnRevenueModifiedBudgetLine $line): bool =>
            ($filters['chapter_code'] === null || $line->chapter_code === $filters['chapter_code'])
            && ($filters['specific_item_code'] === null || $line->specific_item_code === $filters['specific_item_code'])
            && ($filters['month'] === null || $line->month === $filters['month'])
        )->values();
        $rows = $lines->map(fn (OwnRevenueModifiedBudgetLine $line): array => $this->line($line))->all();

        return [
            'budget' => $this->budget($budget),
            'filters' => ['applied' => $filters, 'options' => $this->filterOptions($allLines)],
            'has_initial_budget' => $budget->initialBudget()->exists(),
            'summary' => $this->sumRows($rows),
            'lines' => $rows,
            'planning_vs_execution' => $this->planningVsExecution($rows),
            'modifications' => $this->modifications($budget, $lines->modelKeys()),
            'expense_dossiers' => $this->expenseDossiers($budget, $lines->modelKeys()),
            'fuel' => $this->fuelSummary->forBudget($budget),
        ];
    }
}
```

`line()` debe devolver códigos, nombres, mes y los seis importes como cadenas, reutilizando exclusivamente `OwnRevenueBudgetBalance`. `normalizeFilters()` sólo acepta valores presentes en `$allLines`; un filtro inválido se convierte en `null`. `execution_percentage` se calcula con `bcdiv(bcmul($paid, '100', 2), $initial, 2)` y es `null` cuando el inicial es cero.

- [ ] **Step 5: Verificar GREEN**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php`

Expected: PASS para saldos, aislamiento y precisión.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData.php tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php
git commit -m "feat: calculate own revenue internal reports"
```

### Task 3: Incorporar modificaciones, expedientes y filtros

**Files:**
- Modify: `app/Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php`

- [ ] **Step 1: Escribir pruebas fallidas de filtros y resúmenes operativos**

Probar `chapter_code=2000`, `specific_item_code=21101` y `month=5` por separado y combinados. Afirmar que:

```php
expect($data['filters']['applied'])->toBe([
    'chapter_code' => '2000',
    'specific_item_code' => '21101',
    'month' => 5,
])->and($data['lines'])->toHaveCount(1)
  ->and($data['modifications']['total'])->toBe(1)
  ->and($data['expense_dossiers']['by_status']['paid'])->toBe(1)
  ->and($data['expense_dossiers']['pending_requirements'])->toBe(2);
```

Probar entradas inexistentes y afirmar que las tres quedan normalizadas a `null`.

- [ ] **Step 2: Verificar RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php --filter="filters|operational summaries"`

Expected: FAIL porque faltan los resúmenes completos.

- [ ] **Step 3: Implementar consultas acotadas**

`modifications()` debe incluir movimientos cuyo origen o destino esté en los renglones filtrados, devolver `total`, totales por tipo y los últimos 100 registros. `expenseDossiers()` debe limitarse a los mismos renglones, devolver conteos para cada valor de `OwnRevenueExpenseDossierStatus` y contar requisitos con estado pendiente. Todas las consultas deben incluir `where('own_revenue_budget_id', $budget->id)` además de los identificadores de línea.

El contrato mínimo será:

```php
'modifications' => [
    'total' => $query->count(),
    'transfer_amount_cents' => (string) (clone $query)->where('type', 'transfer')->sum('amount_cents'),
    'rescheduling_amount_cents' => (string) (clone $query)->where('type', 'rescheduling')->sum('amount_cents'),
    'items' => $items,
],
'expense_dossiers' => [
    'total' => $dossiers->count(),
    'by_status' => $statusCounts,
    'pending_requirements' => $pendingRequirements,
],
```

- [ ] **Step 4: Verificar y formatear**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Reports/OwnRevenueInternalReportData.php tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportDataTest.php
git commit -m "feat: summarize own revenue report operations"
```

### Task 4: Exponer el tablero mediante Inertia y Wayfinder

**Files:**
- Create: `app/Http/Controllers/Finance/OwnRevenueInternalReportController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest.php`

- [ ] **Step 1: Generar controlador y prueba**

```bash
php artisan make:controller Finance/OwnRevenueInternalReportController --invokable --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest --no-interaction
```

- [ ] **Step 2: Escribir pruebas fallidas HTTP**

Para manager, assistant y auditor, comprobar respuesta exitosa y componente. Para usuario no autorizado, comprobar `403`. Verificar también filtros normalizados:

```php
$this->actingAs($auditor)
    ->get(route('finance.own-revenue.budgets.reports.show', [
        'budget' => $budget,
        'chapter_code' => '2000',
        'specific_item_code' => '21101',
        'month' => 5,
    ]))
    ->assertSuccessful()
    ->assertInertia(fn (Assert $page) => $page
        ->component('finance/own-revenue/reports/show')
        ->where('budget.id', $budget->id)
        ->where('filters.applied.month', 5)
        ->where('permissions.read_only', true));
```

- [ ] **Step 3: Verificar RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest.php`

Expected: FAIL porque la ruta no existe.

- [ ] **Step 4: Implementar controlador y ruta**

```php
final class OwnRevenueInternalReportController extends Controller
{
    public function __invoke(Request $request, OwnRevenueBudget $budget, OwnRevenueInternalReportData $reports): Response
    {
        Gate::authorize('view', $budget);

        return Inertia::render('finance/own-revenue/reports/show', [
            ...$reports->forBudget($budget, $request->only(['chapter_code', 'specific_item_code', 'month'])),
            'permissions' => ['read_only' => true],
        ]);
    }
}
```

Registrar la ruta GET inmediatamente después de ejecución y generar Wayfinder mediante `php artisan wayfinder:generate --no-interaction`.

- [ ] **Step 5: Verificar GREEN y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Finance/OwnRevenueInternalReportController.php routes/web.php tests/Feature/Finance/OwnRevenue/Reports/OwnRevenueInternalReportNavigationTest.php
git commit -m "feat: expose own revenue internal reports"
```

### Task 5: Construir la pantalla consultable

**Files:**
- Create: `resources/js/pages/finance/own-revenue/reports/show.tsx`
- Modify: `resources/js/pages/finance/own-revenue/budgets/show.tsx`
- Create: `tests/Frontend/own-revenue-internal-reports.test.mjs`

- [ ] **Step 1: Escribir la prueba frontend fallida**

La prueba leerá los archivos fuente y comprobará:

```js
test('internal reports stay read only and preserve filters in the same window', () => {
    assert.match(page, /Inicial/);
    assert.match(page, /Modificado/);
    assert.match(page, /Reservado/);
    assert.match(page, /Comprometido/);
    assert.match(page, /Pagado/);
    assert.match(page, /Disponible/);
    assert.match(page, /router\.get/);
    assert.doesNotMatch(page, /target=["']_blank/);
    assert.doesNotMatch(page, /method=["']post/);
    assert.match(budgetPage, /Abrir reportes/);
});
```

- [ ] **Step 2: Verificar RED**

Run: `node --test tests/Frontend/own-revenue-internal-reports.test.mjs`

Expected: FAIL porque la página no existe.

- [ ] **Step 3: Implementar la página**

Crear tipos locales con todos los importes como `string`. Usar `router.get(reports.show.url(budget.id), nextFilters, { preserveState: true, replace: true })` en cada cambio de filtro. La jerarquía visual será:

```tsx
<AppLayout breadcrumbs={breadcrumbs}>
    <Head title={`Reportes de Ingresos Propios ${budget.fiscal_year}`} />
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <ReportHeader />
        <ReportFilters />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {summaryCards.map((card) => <SummaryCard key={card.label} {...card} />)}
        </div>
        <BudgetBreakdownTable />
        <div className="grid gap-6 xl:grid-cols-2">
            <PlanningExecutionCard />
            <FuelSummaryCard />
            <ModificationSummaryCard />
            <ExpenseDossierSummaryCard />
        </div>
    </div>
</AppLayout>
```

Reutilizar `Card`, `Badge`, `Button`, `Select` y el formateador monetario existente. La tabla llevará `overflow-x-auto`; cada sección mostrará un texto operativo si no hay presupuesto inicial, líneas, modificaciones, expedientes o fondo. No incluir botones de mutación.

`ModificationSummaryCard` mostrará los totales por tipo y una tabla compacta con los elementos devueltos por `modifications.items`. `ExpenseDossierSummaryCard` mostrará los conteos por etapa y los requisitos pendientes. `FuelSummaryCard` indicará explícitamente que capítulo, partida y mes no modifican el resumen del fondo operativo.

En `budgets/show.tsx`, importar la ruta Wayfinder de reportes y agregar una tarjeta visible para estados `initial_authorized`, `in_execution` y `closed` con enlace `reports.show(budget.id)`.

- [ ] **Step 4: Verificar interfaz**

```bash
node --test tests/Frontend/own-revenue-internal-reports.test.mjs
npm run types:check
npm exec prettier -- --write resources/js/pages/finance/own-revenue/reports/show.tsx resources/js/pages/finance/own-revenue/budgets/show.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/finance/own-revenue/reports/show.tsx resources/js/pages/finance/own-revenue/budgets/show.tsx tests/Frontend/own-revenue-internal-reports.test.mjs
git commit -m "feat: add own revenue internal report dashboard"
```

### Task 6: Verificación integral e integración

**Files:**
- Verify all files changed by Tasks 1–5.

- [ ] **Step 1: Ejecutar pruebas focalizadas**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Reports tests/Feature/Finance/OwnRevenue/Execution/OwnRevenueExecutionWorkspaceTest.php tests/Feature/Finance/OwnRevenue/Fuel
npm run test:frontend
```

Expected: PASS.

- [ ] **Step 2: Ejecutar controles de calidad**

```bash
vendor/bin/pint --dirty --format agent
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/reports/show.tsx resources/js/pages/finance/own-revenue/budgets/show.tsx tests/Frontend/own-revenue-internal-reports.test.mjs
npm run build
git diff --check
```

Expected: todos los comandos terminan con código `0`. La advertencia local de Node 20.17 frente a la recomendación de Vite no invalida una compilación exitosa.

- [ ] **Step 3: Verificar ruta y navegador**

```bash
php artisan route:list --name=own-revenue.budgets.reports
```

Resolver la URL con Laravel Boost, abrir el tablero mediante Herd y confirmar: filtros en la misma ventana, ausencia de acciones de modificación, diseño móvil/oscuro y ausencia de errores recientes en navegador.

- [ ] **Step 4: Revisar alcance**

Confirmar que no se agregaron tablas, exportaciones, cierre anual ni filtro de actividad. Revisar `git status --short` y el rango completo contra `docs/superpowers/specs/2026-07-18-reportes-internos-ingresos-propios-design.md`.

- [ ] **Step 5: Cerrar rama**

Usar `superpowers:requesting-code-review`, corregir hallazgos confirmados, repetir la verificación y después usar `superpowers:finishing-a-development-branch` para integrar a `main`, publicar el remoto y limpiar el worktree.
