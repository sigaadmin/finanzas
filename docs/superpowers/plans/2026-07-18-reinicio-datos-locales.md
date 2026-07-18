# Centro de reinicio de datos locales Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Incorporar un centro administrativo y un comando Artisan que reinicien de forma segura y aislada los datos locales de Ventanilla, U300, Ingresos Propios o toda la aplicación.

**Architecture:** Un catálogo tipado define los alcances, órdenes de borrado y raíces de archivos; una acción transaccional única ejecuta el reinicio y es consumida por HTTP y Artisan. Un middleware y la propia acción bloquean cualquier uso fuera de `local`, mientras que la interfaz Inertia exige propietario y frases exactas.

**Tech Stack:** Laravel 13, PHP 8.5, SQLite/MySQL-compatible Query Builder, Inertia v3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

## Mapa de archivos

- `app/Enums/Settings/LocalDataResetScope.php`: valores permitidos, frases y etiquetas de los cuatro alcances.
- `app/Data/Settings/LocalDataResetResult.php`: resultado inmutable compartido por web y consola.
- `app/Services/Settings/LocalDataResetCatalog.php`: inventario explícito de tablas y directorios.
- `app/Actions/Settings/EnsureInstitutionalOwner.php`: provisión única del propietario institucional.
- `app/Actions/Settings/ResetLocalData.php`: transacción, conteos y limpieza posterior de archivos.
- `app/Console/Commands/ResetLocalDataCommand.php`: adaptador Artisan con confirmación.
- `app/Http/Middleware/EnsureLocalDataResetIsAvailable.php`: respuesta 404 fuera de `local`.
- `app/Http/Requests/Settings/ResetLocalDataRequest.php`: propietario y frase exacta.
- `app/Http/Controllers/Settings/LocalDataResetController.php`: pantalla y ejecución web.
- `resources/js/pages/settings/local-data.tsx`: página administrativa.
- `resources/js/components/settings/local-data-reset-card.tsx`: bloque reutilizable de confirmación.
- `resources/js/components/settings/local-data-reset-state.js`: reglas puras de habilitación.
- `resources/js/layouts/settings/layout.tsx`: navegación condicional.
- `app/Http/Middleware/HandleInertiaRequests.php` y `resources/js/types/global.d.ts`: disponibilidad compartida.
- `routes/settings.php`: rutas nombradas para consulta y reinicio.
- `database/seeders/DatabaseSeeder.php`: reutiliza la provisión institucional.
- `tests/Feature/Settings/LocalDataResetCatalogTest.php`: cobertura del inventario.
- `tests/Feature/Settings/ResetLocalDataTest.php`: aislamiento, archivos, rollback y reinicio total.
- `tests/Feature/Settings/LocalDataResetHttpTest.php`: ambiente, permisos, validación y respuesta.
- `tests/Feature/Settings/ResetLocalDataCommandTest.php`: contrato Artisan.
- `tests/Frontend/local-data-reset.test.mjs`: comportamiento y lenguaje de la interfaz.

### Task 1: Definir el contrato y cerrar el inventario

**Files:**
- Create: `app/Enums/Settings/LocalDataResetScope.php`
- Create: `app/Data/Settings/LocalDataResetResult.php`
- Create: `app/Services/Settings/LocalDataResetCatalog.php`
- Test: `tests/Feature/Settings/LocalDataResetCatalogTest.php`

- [ ] **Step 1: Generar las clases con Artisan**

Run:

```bash
php artisan make:enum Settings/LocalDataResetScope --no-interaction
php artisan make:class Data/Settings/LocalDataResetResult --no-interaction
php artisan make:class Services/Settings/LocalDataResetCatalog --no-interaction
php artisan make:test --pest Settings/LocalDataResetCatalogTest --no-interaction
```

Expected: se crean los cuatro archivos sin modificar dependencias.

- [ ] **Step 2: Escribir primero las pruebas del contrato y cobertura**

Agregar a `tests/Feature/Settings/LocalDataResetCatalogTest.php` estas pruebas de contrato:

```php
use App\Enums\Settings\LocalDataResetScope;
use App\Services\Settings\LocalDataResetCatalog;
use Illuminate\Support\Facades\Schema;

test('reset scopes expose their exact confirmation phrases', function () {
    expect(LocalDataResetScope::Ventanilla->confirmationPhrase())->toBe('BORRAR VENTANILLA')
        ->and(LocalDataResetScope::U300->confirmationPhrase())->toBe('BORRAR U300')
        ->and(LocalDataResetScope::OwnRevenue->confirmationPhrase())->toBe('BORRAR INGRESOS PROPIOS')
        ->and(LocalDataResetScope::All->confirmationPhrase())->toBe('REINICIAR TODO');
});

test('every application table has an explicit reset decision', function () {
    $schemaTables = collect(Schema::getTableListing())
        ->map(fn (string $table): string => str_contains($table, '.') ? str($table)->afterLast('.')->value() : $table)
        ->reject(fn (string $table): bool => $table === 'migrations' || str_starts_with($table, 'sqlite_'))
        ->sort()->values()->all();

    expect(app(LocalDataResetCatalog::class)->applicationTables())
        ->sort()->values()->all()
        ->toBe($schemaTables);
});
```

- [ ] **Step 3: Ejecutar la prueba y comprobar el fallo**

Run: `php artisan test --compact tests/Feature/Settings/LocalDataResetCatalogTest.php`

Expected: FAIL porque el enum y el catálogo aún no exponen el contrato.

- [ ] **Step 4: Implementar el enum y el resultado inmutable**

Implementar el enum con este contrato:

```php
enum LocalDataResetScope: string
{
    case Ventanilla = 'ventanilla';
    case U300 = 'u300';
    case OwnRevenue = 'own-revenue';
    case All = 'all';

    public function confirmationPhrase(): string
    {
        return match ($this) {
            self::Ventanilla => 'BORRAR VENTANILLA',
            self::U300 => 'BORRAR U300',
            self::OwnRevenue => 'BORRAR INGRESOS PROPIOS',
            self::All => 'REINICIAR TODO',
        };
    }
}
```

Implementar `LocalDataResetResult` como `final readonly class` con:

```php
public function __construct(
    public LocalDataResetScope $scope,
    public int $deletedRecords,
    /** @var list<string> */
    public array $fileWarnings = [],
) {}
```

- [ ] **Step 5: Implementar el catálogo explícito**

Crear constantes privadas con los órdenes hijo-a-padre documentados en la especificación y exponer:

```php
/** @return list<string> */
public function tablesFor(LocalDataResetScope $scope): array;

/** @return list<array{disk: string, path: string}> */
public function fileRootsFor(LocalDataResetScope $scope): array;

/** @return Collection<int, string> */
public function applicationTables(): Collection;
```

`tablesFor(All)` debe unir, sin duplicados, los tres módulos y las tablas de acceso, infraestructura, catálogos y folios. `applicationTables()` debe ser la misma unión; no debe consultar nombres dinámicos para decidir qué borrar.

- [ ] **Step 6: Ejecutar la prueba y formatear**

Run:

```bash
php artisan test --compact tests/Feature/Settings/LocalDataResetCatalogTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS; Pint no deja cambios de estilo pendientes.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/Settings/LocalDataResetScope.php app/Data/Settings/LocalDataResetResult.php app/Services/Settings/LocalDataResetCatalog.php tests/Feature/Settings/LocalDataResetCatalogTest.php
git commit -m "feat: define local data reset scopes"
```

### Task 2: Implementar reinicios parciales transaccionales

**Files:**
- Create: `app/Actions/Settings/ResetLocalData.php`
- Test: `tests/Feature/Settings/ResetLocalDataTest.php`

- [ ] **Step 1: Generar acción y prueba**

Run:

```bash
php artisan make:class Actions/Settings/ResetLocalData --no-interaction
php artisan make:test --pest Settings/ResetLocalDataTest --no-interaction
```

Expected: ambos archivos son creados.

- [ ] **Step 2: Escribir pruebas de aislamiento y archivos**

Las pruebas deben cambiar temporalmente el ambiente y restaurarlo:

```php
beforeEach(fn () => app()->detectEnvironment(fn (): string => 'local'));
afterEach(fn () => app()->detectEnvironment(fn (): string => 'testing'));
```

Crear registros testigo mediante factories para Ventanilla, U300 e Ingresos Propios, archivos con `Storage::fake('local')` y `Storage::fake('public')`, y verificar:

```php
$result = app(ResetLocalData::class)->handle(LocalDataResetScope::U300);

expect($result->scope)->toBe(LocalDataResetScope::U300)
    ->and(U300Program::query()->count())->toBe(0)
    ->and(OwnRevenueBudget::query()->count())->toBe(1)
    ->and(PaymentProcedure::query()->count())->toBe(1);

Storage::disk('local')->assertMissing('u300/imports/source.pdf');
Storage::disk('public')->assertMissing('u300/technical-sheets/reference-photos/photo.jpg');
Storage::disk('local')->assertExists('own-revenue/imports/keep.xlsx');
```

Agregar dos pruebas separadas con estas afirmaciones exactas:

- Ventanilla deja en cero `PaymentProcedure`, `PaymentTransaction`, `Receipt`, `SeqDeposit` y `SeqReportExport`; conserva un `ChargeConcept`, un `OfficialFeeSchedule`, el programa U300, el presupuesto de Ingresos Propios y el usuario; elimina las secuencias `procedure`, `receipt_internal`, `receipt_external` y conserva una secuencia testigo `future_module`.
- Ingresos Propios deja en cero `OwnRevenueBudget`, sus importaciones y sus datos de ejecución; conserva `ExpenseClassification`, `finance/expense-classifications/imports/source.xlsx`, el programa U300, el trámite de Ventanilla y los usuarios; elimina `own-revenue/imports`, `own-revenue/exports` y `finance/own-revenue`.

Simular además un fallo posterior al commit y comprobar que la base queda limpia, el resultado contiene advertencias y no se lanza una excepción que sugiera rollback:

```php
Storage::shouldReceive('disk')->with('local')->andThrow(new RuntimeException('disk unavailable'));

$result = app(ResetLocalData::class)->handle(LocalDataResetScope::OwnRevenue);

expect(OwnRevenueBudget::query()->count())->toBe(0)
    ->and($result->fileWarnings)->not->toBeEmpty();
```

- [ ] **Step 3: Escribir la prueba de rollback antes de archivos**

En SQLite, instalar un trigger que impida el borrado de `own_revenue_budgets`:

```php
DB::statement("CREATE TRIGGER block_budget_delete BEFORE DELETE ON own_revenue_budgets BEGIN SELECT RAISE(ABORT, 'blocked'); END");

expect(fn () => app(ResetLocalData::class)->handle(LocalDataResetScope::OwnRevenue))
    ->toThrow(QueryException::class);

expect(OwnRevenueBudget::query()->count())->toBe(1);
Storage::disk('local')->assertExists('own-revenue/imports/keep.xlsx');
```

- [ ] **Step 4: Ejecutar las pruebas y comprobar el fallo**

Run: `php artisan test --compact tests/Feature/Settings/ResetLocalDataTest.php`

Expected: FAIL porque la acción no implementa `handle`.

- [ ] **Step 5: Implementar la acción mínima para alcances parciales**

La estructura central será:

```php
public function handle(LocalDataResetScope $scope): LocalDataResetResult
{
    throw_unless(app()->environment('local'), LogicException::class, 'El reinicio sólo está disponible en local.');

    if ($scope === LocalDataResetScope::All) {
        throw new LogicException('El reinicio general todavía no está habilitado.');
    }

    $deletedRecords = DB::transaction(function () use ($scope): int {
        $deleted = 0;

        foreach ($this->catalog->tablesFor($scope) as $table) {
            $deleted += DB::table($table)->delete();
        }

        if ($scope === LocalDataResetScope::Ventanilla) {
            $deleted += DB::table('finance_folio_sequences')
                ->whereIn('sequence_key', ['procedure', 'receipt_internal', 'receipt_external'])
                ->delete();
        }

        return $deleted;
    });

    return new LocalDataResetResult($scope, $deletedRecords, $this->deleteFileRoots($scope));
}
```

`deleteFileRoots()` recorrerá únicamente el catálogo, usará `Storage::disk($disk)->deleteDirectory($path)`, atrapará `Throwable`, registrará `Log::warning` sin datos personales y agregará una advertencia legible.

- [ ] **Step 6: Ejecutar pruebas y formato**

Run:

```bash
php artisan test --compact tests/Feature/Settings/ResetLocalDataTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS para los tres alcances parciales, aislamiento, archivos y rollback.

- [ ] **Step 7: Commit**

```bash
git add app/Actions/Settings/ResetLocalData.php tests/Feature/Settings/ResetLocalDataTest.php
git commit -m "feat: reset local finance modules safely"
```

### Task 3: Completar reinicio general y comando Artisan

**Files:**
- Create: `app/Actions/Settings/EnsureInstitutionalOwner.php`
- Create: `app/Console/Commands/ResetLocalDataCommand.php`
- Modify: `app/Actions/Settings/ResetLocalData.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `tests/Feature/Settings/ResetLocalDataTest.php`
- Test: `tests/Feature/Settings/ResetLocalDataCommandTest.php`

- [ ] **Step 1: Generar acción, comando y prueba**

Run:

```bash
php artisan make:class Actions/Settings/EnsureInstitutionalOwner --no-interaction
php artisan make:command ResetLocalDataCommand --command=finance:reset-local-data --no-interaction
php artisan make:test --pest Settings/ResetLocalDataCommandTest --no-interaction
```

Expected: Laravel crea los archivos en sus ubicaciones convencionales.

- [ ] **Step 2: Escribir pruebas del reinicio general**

Agregar un segundo usuario/acceso, datos en los tres módulos, catálogos y archivos. Después:

```php
$result = app(ResetLocalData::class)->handle(LocalDataResetScope::All);

expect($result->deletedRecords)->toBeGreaterThan(0)
    ->and(User::query()->pluck('email')->all())->toBe(['administrador.siga@crenfcp.edu.mx'])
    ->and(AuthorizedAccess::query()->value('role'))->toBe(UserRole::Owner)
    ->and(DB::table('migrations')->count())->toBeGreaterThan(0)
    ->and(ExpenseClassification::query()->count())->toBe(0)
    ->and(ChargeConcept::query()->count())->toBe(0);
```

Verificar que se eliminan también `finance/expense-classifications/imports`, las raíces U300 y las raíces de Ingresos Propios.

- [ ] **Step 3: Escribir pruebas del comando**

Cubrir selección inválida, cancelación, `--force` y bloqueo fuera de local:

```php
$this->artisan('finance:reset-local-data', ['scope' => 'u300'])
    ->expectsConfirmation('Esta operación eliminará permanentemente los datos locales de U300. ¿Desea continuar?', 'no')
    ->assertExitCode(1);

$this->artisan('finance:reset-local-data', ['scope' => 'u300', '--force' => true])
    ->expectsOutputToContain('U300 se reinició correctamente')
    ->assertSuccessful();
```

- [ ] **Step 4: Ejecutar las pruebas y comprobar el fallo**

Run: `php artisan test --compact tests/Feature/Settings/ResetLocalDataTest.php tests/Feature/Settings/ResetLocalDataCommandTest.php`

Expected: FAIL porque `All`, la provisión y el comando no están completos.

- [ ] **Step 5: Extraer la provisión institucional**

`EnsureInstitutionalOwner::handle()` contendrá el `updateOrCreate` de `AuthorizedAccess` y `firstOrCreate` de `User` que hoy vive en `DatabaseSeeder`. El seeder se reducirá a:

```php
public function run(EnsureInstitutionalOwner $ensureOwner): void
{
    $ensureOwner->handle();
}
```

- [ ] **Step 6: Implementar el alcance general dentro de la misma transacción**

Modificar `ResetLocalData` para que `All` borre el inventario completo, elimine todas las secuencias y llame a `$this->ensureOwner->handle()` antes del commit:

```php
if ($scope === LocalDataResetScope::All) {
    foreach ($this->catalog->tablesFor($scope) as $table) {
        $deleted += DB::table($table)->delete();
    }

    $this->ensureOwner->handle();
}
```

El orden del catálogo debe situar `passkeys` antes de `users` y todos los datos de dominio antes de borrar usuarios, respetando las claves foráneas sin desactivarlas.

- [ ] **Step 7: Implementar el comando**

Usar la firma:

```php
protected $signature = 'finance:reset-local-data
    {scope : ventanilla, u300, own-revenue o all}
    {--force : Ejecutar sin confirmación interactiva}';
```

`handle(ResetLocalData $reset): int` debe convertir con `LocalDataResetScope::tryFrom`, rechazar valores desconocidos, pedir confirmación salvo `--force`, ejecutar la acción y mostrar registros eliminados y cada advertencia. Devolver `self::FAILURE` al cancelar o fallar y `self::SUCCESS` al completar.

- [ ] **Step 8: Ejecutar pruebas y formato**

Run:

```bash
php artisan test --compact tests/Feature/Settings/ResetLocalDataTest.php tests/Feature/Settings/ResetLocalDataCommandTest.php tests/Feature/Finance/AuthorizedAccessTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS, incluida la prueba existente del seeder.

- [ ] **Step 9: Commit**

```bash
git add app/Actions/Settings/EnsureInstitutionalOwner.php app/Actions/Settings/ResetLocalData.php app/Console/Commands/ResetLocalDataCommand.php database/seeders/DatabaseSeeder.php tests/Feature/Settings/ResetLocalDataTest.php tests/Feature/Settings/ResetLocalDataCommandTest.php
git commit -m "feat: add complete local reset command"
```

### Task 4: Proteger y exponer el flujo HTTP

**Files:**
- Create: `app/Http/Middleware/EnsureLocalDataResetIsAvailable.php`
- Create: `app/Http/Requests/Settings/ResetLocalDataRequest.php`
- Create: `app/Http/Controllers/Settings/LocalDataResetController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/global.d.ts`
- Modify: `routes/settings.php`
- Test: `tests/Feature/Settings/LocalDataResetHttpTest.php`

- [ ] **Step 1: Generar archivos HTTP y prueba**

Run:

```bash
php artisan make:middleware EnsureLocalDataResetIsAvailable --no-interaction
php artisan make:request Settings/ResetLocalDataRequest --no-interaction
php artisan make:controller Settings/LocalDataResetController --no-interaction
php artisan make:test --pest Settings/LocalDataResetHttpTest --no-interaction
```

Expected: archivos generados con namespaces convencionales.

- [ ] **Step 2: Escribir las pruebas de ambiente, propietario y frase**

Cubrir:

```php
test('local data reset responds as not found outside local', function () {
    $owner = ownerUser();
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs($owner)->get(route('local-data.index'))->assertNotFound();
});

test('only the owner can open local data reset', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $this->actingAs(User::factory()->create())
        ->get(route('local-data.index'))
        ->assertForbidden();
});

test('the exact phrase is required before resetting a scope', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $this->actingAs(ownerUser())
        ->post(route('local-data.reset', 'u300'), ['confirmation' => 'BORRAR'])
        ->assertSessionHasErrors('confirmation');

    expect(U300Program::query()->count())->toBe(1);
});
```

Agregar un caso exitoso parcial con flash y otro de `all` que compruebe redirección al inicio e invalidación de la sesión.

- [ ] **Step 3: Ejecutar la prueba y comprobar el fallo**

Run: `php artisan test --compact tests/Feature/Settings/LocalDataResetHttpTest.php`

Expected: FAIL porque no existen rutas ni controlador.

- [ ] **Step 4: Implementar middleware, request y controlador**

El middleware hará:

```php
abort_unless(app()->environment('local'), 404);

return $next($request);
```

El request autorizará con `$this->user()?->isOwner() === true`, convertirá el parámetro `scope` mediante `LocalDataResetScope::tryFrom` y validará `confirmation` con `Rule::in([$scope->confirmationPhrase()])`.

El controlador expondrá:

```php
public function index(Request $request): Response;
public function store(ResetLocalDataRequest $request, string $scope, ResetLocalData $reset): RedirectResponse;
```

`index` rechazará no propietarios y enviará al componente `settings/local-data` una lista serializable con `value`, `label`, `description`, `preserves`, `confirmation_phrase` y `is_global`. `store` ejecutará el servicio; en parciales regresará con flash `success` y `warning`, y en `all` cerrará/invalidate/regenerará token de sesión antes de redirigir a `home`.

- [ ] **Step 5: Declarar rutas estables y prop compartida**

En `routes/settings.php` agregar dentro de `auth`:

```php
Route::middleware(EnsureLocalDataResetIsAvailable::class)->group(function () {
    Route::get('settings/local-data', [LocalDataResetController::class, 'index'])
        ->name('local-data.index');
    Route::post('settings/local-data/{scope}', [LocalDataResetController::class, 'store'])
        ->whereIn('scope', array_column(LocalDataResetScope::cases(), 'value'))
        ->name('local-data.reset');
});
```

Compartir `localDataResetAvailable` como `app()->environment('local') && $request->user()?->isOwner() === true` y añadirlo como `boolean` a `resources/js/types/global.d.ts`.

- [ ] **Step 6: Generar Wayfinder y ejecutar pruebas**

Run:

```bash
php artisan wayfinder:generate
php artisan test --compact tests/Feature/Settings/LocalDataResetHttpTest.php tests/Feature/Settings/AppearanceSettingsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: PASS; se generan `@/routes/local-data` y las rutas mantienen sus nombres.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/EnsureLocalDataResetIsAvailable.php app/Http/Requests/Settings/ResetLocalDataRequest.php app/Http/Controllers/Settings/LocalDataResetController.php app/Http/Middleware/HandleInertiaRequests.php resources/js/types/global.d.ts routes/settings.php tests/Feature/Settings/LocalDataResetHttpTest.php
git commit -m "feat: expose owner-only local reset endpoints"
```

### Task 5: Construir la interfaz de Datos locales

**Files:**
- Create: `resources/js/components/settings/local-data-reset-state.js`
- Create: `resources/js/components/settings/local-data-reset-card.tsx`
- Create: `resources/js/pages/settings/local-data.tsx`
- Modify: `resources/js/layouts/settings/layout.tsx`
- Test: `tests/Frontend/local-data-reset.test.mjs`

- [ ] **Step 1: Escribir la prueba frontend**

Crear `tests/Frontend/local-data-reset.test.mjs`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { canSubmitLocalDataReset } from '../../resources/js/components/settings/local-data-reset-state.js';

test('enables reset only for the exact confirmation phrase while idle', () => {
    assert.equal(canSubmitLocalDataReset('BORRAR U300', 'BORRAR U300', false), true);
    assert.equal(canSubmitLocalDataReset('borrar u300', 'BORRAR U300', false), false);
    assert.equal(canSubmitLocalDataReset('BORRAR U300', 'BORRAR U300', true), false);
});
```

Agregar una inspección del texto de la página que confirme las cuatro operaciones y que no contenga `truncate`, `DROP TABLE`, `parser`, nombres de tablas ni variables técnicas.

- [ ] **Step 2: Ejecutar la prueba y comprobar el fallo**

Run: `node --test --test-name-pattern="reset" tests/Frontend/local-data-reset.test.mjs`

Expected: FAIL porque el helper y la página no existen.

- [ ] **Step 3: Implementar el helper y la tarjeta**

El helper será puro:

```js
export function canSubmitLocalDataReset(value, expected, processing) {
    return !processing && value === expected;
}
```

La tarjeta recibirá la definición del servidor, mantendrá `confirmation` y `dialogOpen`, y usará `useForm({ confirmation: '' })`. El primer botón abrirá un `Dialog`; el botón destructivo del diálogo llamará:

```tsx
form.post(resetRoute(scope.value).url, {
    preserveScroll: true,
    onSuccess: () => {
        setDialogOpen(false);
        form.reset();
    },
});
```

Mostrar descripción funcional, datos conservados, frase requerida, error de validación, estado “Reiniciando…” y advertencia más severa para `is_global`.

- [ ] **Step 4: Implementar la página y navegación condicional**

`settings/local-data.tsx` debe usar `SettingsLayout`, `Head`, `Heading` y una cuadrícula de tarjetas sin anidar decoraciones innecesarias. En `settings/layout.tsx`, obtener `localDataResetAvailable` con `usePage()` y agregar **Datos locales** sólo cuando sea `true`, usando `index` de `@/routes/local-data`.

- [ ] **Step 5: Ejecutar pruebas, tipos y formato**

Run:

```bash
npm run test:frontend
npm run types:check
npm exec prettier -- --write resources/js/pages/settings/local-data.tsx resources/js/components/settings/local-data-reset-card.tsx resources/js/layouts/settings/layout.tsx
```

Expected: pruebas y TypeScript PASS; Prettier deja los archivos estables.

- [ ] **Step 6: Ejecutar la prueba HTTP de la página**

Run: `php artisan test --compact tests/Feature/Settings/LocalDataResetHttpTest.php`

Expected: PASS y el componente Inertia recibe los cuatro alcances.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/settings/local-data-reset-state.js resources/js/components/settings/local-data-reset-card.tsx resources/js/pages/settings/local-data.tsx resources/js/layouts/settings/layout.tsx tests/Frontend/local-data-reset.test.mjs
git commit -m "feat: add local data reset center"
```

### Task 6: Verificación integral y publicación

**Files:**
- Modify if needed: files changed in Tasks 1-5 only

- [ ] **Step 1: Ejecutar toda la batería dirigida una sola vez**

Run:

```bash
php artisan test --compact tests/Feature/Settings/LocalDataResetCatalogTest.php tests/Feature/Settings/ResetLocalDataTest.php tests/Feature/Settings/ResetLocalDataCommandTest.php tests/Feature/Settings/LocalDataResetHttpTest.php tests/Feature/Settings/AppearanceSettingsTest.php tests/Feature/Finance/AuthorizedAccessTest.php
npm run test:frontend
```

Expected: todas las pruebas PASS.

- [ ] **Step 2: Ejecutar controles de calidad**

Run:

```bash
vendor/bin/pint --dirty --format agent
npm run types:check
npm run lint:check
npm run build
git diff --check
```

Expected: todos los comandos terminan con código 0 y Vite produce el bundle.

- [ ] **Step 3: Comprobar manualmente que el comando está registrado sin ejecutarlo**

Run:

```bash
php artisan help finance:reset-local-data
php artisan route:list --name=local-data
```

Expected: ayuda con los cuatro scopes y dos rutas `local-data.index` / `local-data.reset` protegidas por middleware.

- [ ] **Step 4: Revisar el alcance Git**

Run: `git status -sb && git diff --stat`

Expected: sólo hay cambios del centro de reinicio; no hay archivos de base SQLite, `.env`, uploads reales ni artefactos de compilación rastreados.

- [ ] **Step 5: Commit de correcciones finales, si existen**

```bash
git add app database/seeders routes resources/js tests docs/superpowers/specs/2026-07-18-reinicio-datos-locales-design.md
git commit -m "test: verify local data reset center"
```

Omitir este commit si `git status` ya está limpio.

- [ ] **Step 6: Publicar `main`**

Run: `git push origin main`

Expected: `main` y `origin/main` quedan sincronizadas.
