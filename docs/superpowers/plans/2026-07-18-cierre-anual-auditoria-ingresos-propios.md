# Cierre anual y auditoría de Ingresos Propios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir el cierre anual definitivo de un presupuesto de Ingresos Propios mediante un acta inmutable y consultar en una sola cronología sus eventos auditables.

**Architecture:** Un servicio de revisión calcula impedimentos y el resumen vigente; una acción transaccional vuelve a calcularlos bajo bloqueo, crea un acta única y cambia el presupuesto a `closed`. Un servicio independiente proyecta los registros auditables existentes a un contrato cronológico común, sin duplicarlos en otra tabla.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, Inertia Laravel 3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

## Estructura de archivos

- `OwnRevenueBudgetClosure`: acta inmutable y relación uno a uno con el presupuesto.
- `OwnRevenueAnnualCloseReview`: única fuente de impedimentos y fotografía financiera del cierre.
- `CloseOwnRevenueBudget`: autorización, bloqueo, revalidación y persistencia atómica.
- `OwnRevenueAuditTimeline`: adaptadores de lectura para eventos existentes.
- `OwnRevenueAnnualCloseController`: revisión y confirmación del cierre.
- `OwnRevenueAuditController`: consulta filtrable del historial.
- `annual-close/show.tsx`: revisión, modal irreversible y acta cerrada.
- `audit/index.tsx`: historial filtrable en la misma ventana.

### Task 1: Persistir el acta anual inmutable

**Files:**
- Create: `database/migrations/2026_07_18_000000_create_own_revenue_budget_closures_table.php`
- Create: `app/Models/Finance/OwnRevenue/OwnRevenueBudgetClosure.php`
- Create: `database/factories/Finance/OwnRevenue/OwnRevenueBudgetClosureFactory.php`
- Modify: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueBudgetClosureSchemaTest.php`

- [ ] **Step 1: Crear la prueba roja de esquema e inmutabilidad**

```php
use App\Models\Finance\OwnRevenue\OwnRevenueBudgetClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('annual close records are unique immutable and auditable', function () {
    expect(Schema::hasColumns('own_revenue_budget_closures', [
        'own_revenue_budget_id', 'note', 'snapshot', 'fingerprint',
        'closed_by', 'closed_at',
    ]))->toBeTrue();

    $closure = OwnRevenueBudgetClosure::factory()->create();

    expect($closure->budget->annualClosure->is($closure))->toBeTrue()
        ->and($closure->snapshot)->toBeArray()
        ->and($closure->closed_at)->not->toBeNull()
        ->and(fn () => $closure->update(['note' => 'Alterada']))->toThrow(LogicException::class)
        ->and(fn () => $closure->delete())->toThrow(LogicException::class);
});
```

- [ ] **Step 2: Ejecutar la prueba y confirmar que falla por tabla/clase inexistente**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueBudgetClosureSchemaTest.php`

Expected: FAIL porque `OwnRevenueBudgetClosure` todavía no existe.

- [ ] **Step 3: Generar migración, modelo y fábrica**

Run:

```bash
php artisan make:model Finance/OwnRevenue/OwnRevenueBudgetClosure --factory --no-interaction
php artisan make:migration create_own_revenue_budget_closures_table --create=own_revenue_budget_closures --no-interaction
```

Implementar la migración con clave foránea única al presupuesto, `text('note')`, `json('snapshot')`, `char('fingerprint', 64)`, clave foránea `closed_by`, `timestamp('closed_at')` y timestamps.

Implementar el modelo con casts `snapshot => array`, `closed_at => datetime`, relaciones `budget()` y `closedBy()`, y eventos `updating`/`deleting` que lancen `LogicException('El acta anual es inmutable.')`. Agregar este método para verificar la huella con la misma serialización estable usada por la acción:

```php
public function canonicalSnapshot(): string
{
    return json_encode(
        Arr::sortRecursive($this->snapshot),
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
}
```

Agregar al presupuesto:

```php
/** @return HasOne<OwnRevenueBudgetClosure, $this> */
public function annualClosure(): HasOne
{
    return $this->hasOne(OwnRevenueBudgetClosure::class);
}
```

- [ ] **Step 4: Ejecutar prueba y formatear**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueBudgetClosureSchemaTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Finance/OwnRevenue database/migrations database/factories/Finance/OwnRevenue tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueBudgetClosureSchemaTest.php
git commit -m "feat: persist immutable own revenue annual closures"
```

### Task 2: Calcular revisión, impedimentos y fotografía de cierre

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseReview.php`
- Test: `tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseReviewTest.php`

- [ ] **Step 1: Escribir pruebas rojas para cada regla de elegibilidad**

Crear presupuestos con líneas modificadas y probar por separado:

```php
$review = app(OwnRevenueAnnualCloseReview::class)->forBudget($budget);

expect($review['eligible'])->toBeFalse()
    ->and($review['blockers'])->toContainEqual([
        'type' => 'active_expense_dossiers',
        'count' => 1,
        'message' => 'Hay 1 expediente que todavía requiere concluirse.',
    ]);
```

Agregar casos para requisito pendiente, comisión pendiente, estados terminales, remanentes permitidos, estado anual no elegible, aislamiento entre presupuestos y presupuesto ya cerrado.

- [ ] **Step 2: Ejecutar la prueba y confirmar el fallo por servicio inexistente**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseReviewTest.php`

Expected: FAIL porque `OwnRevenueAnnualCloseReview` no existe.

- [ ] **Step 3: Implementar el servicio de revisión**

El método público será:

```php
/** @return array<string, mixed> */
public function forBudget(OwnRevenueBudget $budget): array
```

Usará `OwnRevenueInternalReportData::forBudget($budget, [])` para los saldos y resúmenes. Consultará expedientes no terminales, requisitos `pending` limitados con `whereHas('dossier', fn ($query) => $query->where('own_revenue_budget_id', $budget->id))` y comisiones `pending` mediante el fondo del mismo presupuesto.

Devolverá:

```php
[
    'eligible' => $stateIsEligible && $blockers === [],
    'state_is_eligible' => $stateIsEligible,
    'confirmation_phrase' => "CERRAR {$budget->fiscal_year}",
    'blockers' => $blockers,
    'snapshot' => [
        'schema_version' => 1,
        'budget' => [
            'id' => $budget->id,
            'fiscal_year' => $budget->fiscal_year,
            'region_code' => $budget->region_code,
            'region_name' => $budget->region_name,
        ],
        'balances' => $report['summary'],
        'expense_dossiers' => $report['expense_dossiers'],
        'fuel' => $report['fuel'],
        'modifications' => [
            'count' => $report['modifications']['total'],
            'transfer_amount_cents' => $report['modifications']['transfer_amount_cents'],
            'rescheduling_amount_cents' => $report['modifications']['rescheduling_amount_cents'],
        ],
        'official_exports_count' => $initialBudget?->workbookExports()->count() ?? 0,
        'initial_authorization' => $initialBudget === null ? null : [
            'id' => $initialBudget->id,
            'authorized_at' => $initialBudget->authorized_at?->toISOString(),
        ],
    ],
]
```

Los mensajes usarán singular/plural y no incluirán nombres técnicos.

- [ ] **Step 4: Ejecutar pruebas y formatear**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseReviewTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Closing tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseReviewTest.php
git commit -m "feat: review own revenue annual close eligibility"
```

### Task 3: Ejecutar el cierre definitivo en una transacción

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Closing/CloseOwnRevenueBudget.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/Closing/StoreOwnRevenueAnnualCloseRequest.php`
- Modify: `app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php`
- Test: `tests/Feature/Finance/OwnRevenue/Closing/CloseOwnRevenueBudgetTest.php`

- [ ] **Step 1: Escribir pruebas rojas de autorización, validación y atomicidad**

Probar que un responsable cierra con `CERRAR 2026` y nota válida; que asistente/auditor no pueden; que frase, nota, estado, impedimentos y repetición se rechazan; que un impedimento insertado después de una revisión previa es detectado por la acción.

La aserción principal será:

```php
$closure = app(CloseOwnRevenueBudget::class)->handle(
    $budget,
    $manager,
    'Cierre revisado y conciliado.',
);

expect($budget->refresh()->status)->toBe(OwnRevenueBudgetStatus::Closed)
    ->and($closure->fingerprint)->toHaveLength(64)
    ->and($closure->closed_by)->toBe($manager->id)
    ->and(hash('sha256', $closure->canonicalSnapshot()))->toBe($closure->fingerprint);
```

- [ ] **Step 2: Ejecutar la prueba y confirmar fallo por acción inexistente**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/CloseOwnRevenueBudgetTest.php`

Expected: FAIL porque la acción no existe.

- [ ] **Step 3: Implementar política, request y acción**

Agregar a la política:

```php
public function closeAnnualBudget(User $user, OwnRevenueBudget $budget): bool
{
    return in_array($budget->status, [
        OwnRevenueBudgetStatus::InitialAuthorized,
        OwnRevenueBudgetStatus::InExecution,
    ], true) && $this->canAdministrate($user);
}
```

El request autorizará con `closeAnnualBudget` y validará:

```php
return [
    'confirmation' => ['required', 'string', Rule::in(["CERRAR {$budget->fiscal_year}"])],
    'note' => ['required', 'string', 'min:10', 'max:1000'],
];
```

La acción usará `DB::transaction`, recuperará el presupuesto con `lockForUpdate`, autorizará al usuario, comprobará que no exista acta, recalculará `OwnRevenueAnnualCloseReview`, lanzará `ValidationException` con mensajes operativos si no es elegible, ordenará recursivamente el snapshot con `Arr::sortRecursive`, serializará con `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, creará el acta y actualizará el estado.

- [ ] **Step 4: Ejecutar pruebas enfocadas y formatear**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/CloseOwnRevenueBudgetTest.php tests/Feature/Finance/OwnRevenue/Execution tests/Feature/Finance/OwnRevenue/Fuel
```

Expected: PASS y las mutaciones existentes continúan rechazando presupuestos cerrados.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Finance/OwnRevenue/Closing app/Http/Requests/Finance/OwnRevenue/Closing app/Policies/Finance/OwnRevenue tests/Feature/Finance/OwnRevenue/Closing/CloseOwnRevenueBudgetTest.php
git commit -m "feat: close own revenue budgets definitively"
```

### Task 4: Exponer revisión, cierre y acta mediante Inertia

**Files:**
- Create: `app/Http/Controllers/Finance/OwnRevenueAnnualCloseController.php`
- Modify: `routes/web.php`
- Modify: `app/Services/Finance/OwnRevenue/OwnRevenueBudgetViewData.php`
- Test: `tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseNavigationTest.php`

- [ ] **Step 1: Escribir pruebas rojas de navegación y endpoints**

Probar `GET` para responsable, asistente y auditor; comprobar `permissions.close`; probar `POST` exitoso, `403` para auditor y redirección a la revisión con mensaje de éxito.

```php
$this->actingAs($manager)
    ->get(route('finance.own-revenue.budgets.annual-close.show', $budget))
    ->assertSuccessful()
    ->assertInertia(fn (Assert $page) => $page
        ->component('finance/own-revenue/annual-close/show', false)
        ->where('review.confirmation_phrase', 'CERRAR 2026')
        ->where('permissions.close', true));
```

- [ ] **Step 2: Ejecutar prueba y verificar 404**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseNavigationTest.php`

Expected: FAIL porque las rutas no existen.

- [ ] **Step 3: Implementar controlador y rutas**

Agregar:

```php
Route::get('own-revenue/budgets/{budget}/annual-close', [OwnRevenueAnnualCloseController::class, 'show'])
    ->name('own-revenue.budgets.annual-close.show');
Route::post('own-revenue/budgets/{budget}/annual-close', [OwnRevenueAnnualCloseController::class, 'store'])
    ->name('own-revenue.budgets.annual-close.store');
```

`show()` autorizará `view`, renderizará la revisión, el acta con responsable y `permissions.close`. `store()` recibirá el form request, ejecutará la acción y redirigirá a `show` con `success`. El tablero anual expondrá `permissions.closeAnnualBudget` y `annual_closure` sin duplicar cálculos.

- [ ] **Step 4: Ejecutar prueba y formatear**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseNavigationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Finance/OwnRevenueAnnualCloseController.php app/Services/Finance/OwnRevenue/OwnRevenueBudgetViewData.php routes/web.php tests/Feature/Finance/OwnRevenue/Closing/OwnRevenueAnnualCloseNavigationTest.php
git commit -m "feat: expose own revenue annual close review"
```

### Task 5: Proyectar el historial consolidado

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Audit/OwnRevenueAuditTimeline.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueAuditController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Audit/OwnRevenueAuditTimelineTest.php`
- Test: `tests/Feature/Finance/OwnRevenue/Audit/OwnRevenueAuditNavigationTest.php`

- [ ] **Step 1: Escribir pruebas rojas de eventos, orden, filtro y aislamiento**

Crear dos presupuestos y eventos representativos. Comprobar contrato común, orden descendente, actor, referencia, exclusión del otro presupuesto y normalización de filtro inválido.

```php
$timeline = app(OwnRevenueAuditTimeline::class)->forBudget($budget, 'expense_dossier');

expect($timeline['applied_type'])->toBe('expense_dossier')
    ->and($timeline['events'])->each(fn ($event) => $event->type->toBe('expense_dossier'))
    ->and(array_column($timeline['events'], 'occurred_at'))->toBe(
        collect($timeline['events'])->pluck('occurred_at')->sortDesc()->values()->all(),
    );
```

- [ ] **Step 2: Ejecutar pruebas y confirmar fallo por servicio/ruta inexistente**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Audit`

Expected: FAIL.

- [ ] **Step 3: Implementar proyección y controlador**

El servicio devolverá `applied_type`, opciones con etiquetas operativas y eventos. Cada adaptador producirá exactamente:

```php
[
    'id' => 'source:'.$model->id,
    'type' => 'category',
    'occurred_at' => $date->toISOString(),
    'title' => 'Título operativo',
    'description' => 'Contexto legible',
    'actor_name' => $actor?->name,
    'reference' => $visibleReference,
]
```

Agregar adaptadores para presupuesto/COG, archivos confirmados, autorización inicial, exportaciones, modificaciones, transiciones de expedientes, apertura de fondo, registro/confirmación de comisiones y acta. Cargar actores con `with`, limitar toda consulta por `own_revenue_budget_id`, concatenar colecciones, filtrar y ordenar por `occurred_at` e `id` descendentes.

Agregar ruta `GET own-revenue/budgets/{budget}/audit` con nombre `finance.own-revenue.budgets.audit.index`. El controlador autorizará `view`, aceptará sólo `type` y renderizará `finance/own-revenue/audit/index`.

- [ ] **Step 4: Ejecutar pruebas y formatear**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Audit
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Audit app/Http/Controllers/Finance/OwnRevenueAuditController.php routes/web.php tests/Feature/Finance/OwnRevenue/Audit
git commit -m "feat: consolidate own revenue audit history"
```

### Task 6: Construir las pantallas de cierre e historial

**Files:**
- Create: `resources/js/pages/finance/own-revenue/annual-close/show.tsx`
- Create: `resources/js/pages/finance/own-revenue/audit/index.tsx`
- Modify: `resources/js/pages/finance/own-revenue/budgets/show.tsx`
- Test: `tests/Frontend/own-revenue-annual-close.test.mjs`
- Test: `tests/Frontend/own-revenue-audit.test.mjs`

- [ ] **Step 1: Escribir pruebas frontend rojas**

Comprobar en código fuente que:

```js
assert.match(closeSource, /CERRAR \{budget\.fiscal_year\}/);
assert.match(closeSource, /Dialog/);
assert.match(closeSource, /form\.post\(/);
assert.doesNotMatch(closeSource, /reopen|reabrir/i);
assert.match(auditSource, /router\.get\(/);
assert.doesNotMatch(auditSource, /target=["']_blank/);
```

Agregar aserciones de mensajes operativos, deshabilitado por impedimentos, nota mínima, acta inmutable, filtros en misma ventana y accesos desde el tablero anual.

- [ ] **Step 2: Ejecutar pruebas y confirmar fallo por páginas inexistentes**

Run: `node --test tests/Frontend/own-revenue-annual-close.test.mjs tests/Frontend/own-revenue-audit.test.mjs`

Expected: FAIL.

- [ ] **Step 3: Implementar pantalla de cierre**

Usar `Head`, `Link`, `useForm`, componentes `Card`, `Badge`, `Button`, `Dialog`, `Input` e `InputError`, además de un `textarea` nativo con el estilo de los formularios hermanos. Mostrar resumen, impedimentos y acta. Abrir el diálogo sólo si `review.eligible && permissions.close`; habilitar envío únicamente con frase exacta, nota recortada de 10–1000 caracteres y formulario inactivo. No incluir reapertura.

- [ ] **Step 4: Implementar historial y accesos**

Usar `router.get(audit.index.url(budget.id), query, { preserveState: true, replace: true })`. Mostrar filtros compactos, eventos con fecha, responsable, referencia y estado vacío. Agregar tarjetas `Revisar cierre anual` e `Historial del ejercicio` al tablero, mediante `Link` sin `target`.

- [ ] **Step 5: Generar Wayfinder y verificar frontend**

Run:

```bash
npm run build
node --test tests/Frontend/own-revenue-annual-close.test.mjs tests/Frontend/own-revenue-audit.test.mjs
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/annual-close/show.tsx resources/js/pages/finance/own-revenue/audit/index.tsx resources/js/pages/finance/own-revenue/budgets/show.tsx tests/Frontend/own-revenue-annual-close.test.mjs tests/Frontend/own-revenue-audit.test.mjs
```

Expected: PASS. La advertencia local de Node 20.17 puede aparecer, pero la compilación debe terminar con código 0.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/finance/own-revenue tests/Frontend/own-revenue-annual-close.test.mjs tests/Frontend/own-revenue-audit.test.mjs
git commit -m "feat: add annual close and audit screens"
```

### Task 7: Verificación integral y cierre de la Fase 5

**Files:**
- Verify: files touched in Tasks 1–6

- [ ] **Step 1: Ejecutar formato PHP**

Run: `vendor/bin/pint --dirty --format agent`

Expected: PASS.

- [ ] **Step 2: Ejecutar pruebas PHP del alcance y regresiones de mutabilidad**

Run:

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Closing tests/Feature/Finance/OwnRevenue/Audit tests/Feature/Finance/OwnRevenue/Execution tests/Feature/Finance/OwnRevenue/Fuel tests/Feature/Finance/OwnRevenue/Reports
```

Expected: PASS.

- [ ] **Step 3: Ejecutar verificación frontend completa**

Run:

```bash
npm run test:frontend
npm run types:check
npm run build
```

Expected: PASS.

- [ ] **Step 4: Verificar rutas, migración y árbol Git**

Run:

```bash
php artisan route:list --name=finance.own-revenue.budgets.annual-close --except-vendor
php artisan route:list --name=finance.own-revenue.budgets.audit --except-vendor
git diff --check
git status -sb
```

Expected: dos rutas de cierre, una de auditoría, sin errores de whitespace y árbol limpio después del commit final.

- [ ] **Step 5: Auditar el diff contra la especificación**

Revisar el rango desde `bcadc34` y confirmar explícitamente: cierre definitivo, una sola acta, bloqueo transaccional, tres impedimentos, remanentes permitidos, historial sin duplicación, mismos permisos de consulta, lenguaje operativo y ausencia de reapertura/exportación/comparación entre ejercicios.

- [ ] **Step 6: Commit final si la verificación produjo ajustes**

```bash
git add app database resources routes tests
git commit -m "fix: complete own revenue annual close verification"
```

- [ ] **Step 7: Integrar y publicar**

Actualizar `main`, integrar mediante avance rápido, repetir las pruebas esenciales sobre el resultado integrado, publicar `origin/main` y retirar el worktree y rama temporal sólo después de confirmar que `HEAD` y `origin/main` coinciden.
