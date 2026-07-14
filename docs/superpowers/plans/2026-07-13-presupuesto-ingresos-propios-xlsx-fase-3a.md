# Presupuesto de Ingresos Propios — Plan de implementación XLSX fase 3A

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar la infraestructura auditable de importaciones parciales, los cinco espacios de carga y el análisis, vista previa, versionado y confirmación del formato ABPRE.

**Architecture:** `OwnRevenueBudget` seguirá siendo el agregado anual. Las sesiones y archivos vivirán en tablas separadas; un lector XLSX sin dependencias nuevas producirá una representación neutral y adaptadores específicos la convertirán en filas normalizadas e incidencias. Confirmar ABPRE creará una versión inmutable de líneas y mensualidades vinculadas a sus filas de origen, sin autorizar todavía el presupuesto inicial.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, `ZipArchive`/`SimpleXML`, almacenamiento privado de Laravel, Inertia Laravel 3, Inertia React 3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

## Alcance y contratos

Incluye creación mediante `XLSX`, sesiones reanudables, carga privada, SHA-256, versiones, detección de los cinco formatos, corrección manual del tipo, análisis de las dos hojas ABPRE, validación COG, región `02-001`, vista previa, incidencias, confirmación transaccional y procedencia hasta fila/hoja.

Hoja de trabajo, ficha técnica, combustible y viáticos podrán cargarse, versionarse, descargarse y clasificarse; quedarán `Pendiente de analizador` hasta sus planes específicos. Esta fase no autoriza el presupuesto inicial, no distribuye recortes, no crea necesidades, comisiones o recorridos y no agrega dependencias.

- Formatos: `abpre`, `work_sheet`, `technical_sheet`, `fuel`, `travel_expenses`.
- Sesiones: `open`, `completed`, `cancelled`.
- Archivos: `uploaded`, `analyzing`, `needs_correction`, `ready`, `confirmed`, `replaced`, `discarded`, `failed`, `parser_pending`.
- Incidencias: `error`, `warning`, `info`.
- El año del presupuesto prevalece sobre el año detectado.
- Meses ABPRE se almacenan en centavos enteros; `Anual` se recalcula desde enero–diciembre.
- Filas que sólo difieren por región se agrupan después de forzar `02-001`, conservando todas sus filas de origen.
- Hash repetido se rechaza; `force_reanalysis=true` crea otra versión.
- El análisis es sin efectos y sin cola en esta fase.

## Task 1: Confirmar documentación y muestras reales

**Files:**
- Read: `docs/superpowers/specs/2026-07-13-presupuesto-ingresos-propios-importacion-xlsx-design.md`
- Read: `app/Services/Finance/CogCatalogSpreadsheetParser.php`
- Read: `app/Http/Controllers/Finance/U300ImportController.php`
- Read: `app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php`
- Read: `resources/js/pages/finance/own-revenue/budgets/create.tsx`
- Read: XLSX reales 2026 y 2027 proporcionados por el usuario

- [ ] **Step 1: Activar habilidades**

Leer completamente `laravel-best-practices`, `pest-testing`, `inertia-react-development`, `wayfinder-development`, `tailwindcss-development` y `spreadsheets:Spreadsheets` antes de modificar sus dominios.

- [ ] **Step 2: Consultar documentación versionada**

Usar Boost `search-docs`:

```text
packages: ["laravel/framework", "inertiajs/inertia-laravel", "@inertiajs/react", "pestphp/pest"]
queries:
  - "uploaded files validation private storage download authorization"
  - "database transactions lock for update idempotent actions"
  - "Inertia React file upload progress form multipart"
  - "Pest uploaded file storage fake authorization"
```

- [ ] **Step 3: Comprobar generadores**

```bash
php artisan make:model --help
php artisan make:request --help
php artisan make:controller --help
php artisan make:policy --help
```

Expected: código `0` y soporte para `--no-interaction`.

- [ ] **Step 4: Registrar firmas reales en los fixtures**

```text
ABPRE: Clave Unidad Responsable | Partida | Enero | Febrero | Marzo | Abril | Mayo | Junio | Julio | Agosto | Septiembre | Octubre | Noviembre | Diciembre | Anual
Justificación: Unidad Responble | Capítulo | Partida | Impacto en Metas | Justificación
Combustible: FECHAS DE LA COMISION | MODELO DE VEHÍCULO | RECORRIDO | IMPORTE
Viáticos: FECHAS DE LA COMISION | NOMBRE DE PERSONAL COMISIONADO | COSTO UMA | VIATICOS | HOSPEDAJE
Ficha técnica: Partida | Cantidad | Unidad | Descripción | Ragión | Costo | Mes Presupuestado
Hoja de trabajo: actividad | concepto | partida | región | enero | febrero | marzo | abril | mayo | junio | julio | agosto | septiembre | octubre | noviembre | diciembre | anual
```

- [ ] **Step 5: Verificar línea base**

```bash
php artisan test --compact
```

Expected: `284 passed`, `2 skipped` o una cifra mayor sin fallos. No crear commit si sólo hubo lecturas.

## Task 2: Crear esquema y modelos de importación

**Files:**
- Create: enums under `app/Enums/Finance/OwnRevenue/Imports`
- Create: migrations for `own_revenue_import_sessions`, `own_revenue_import_files`, `own_revenue_import_rows`, `own_revenue_import_issues`, `own_revenue_import_decisions`, `own_revenue_abpre_lines`, `own_revenue_abpre_months`, `own_revenue_abpre_justifications`, `own_revenue_import_origins`
- Create: corresponding models/factories under `Finance/OwnRevenue/Imports`
- Modify: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php`

- [ ] **Step 1: Generar archivos Laravel**

```bash
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportSession -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportFile -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportRow -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportIssue -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportDecision -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueAbpreLine -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueAbpreMonth -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueAbpreJustification -mf --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueImportOrigin -mf --no-interaction
php artisan make:test --pest OwnRevenueImportSchemaTest --no-interaction
```

Mover la prueba generada a `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php`.

- [ ] **Step 2: Escribir prueba roja del contrato**

```php
test('import schema keeps files rows issues and immutable ABPRE versions', function () {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();
    $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Ready,
        'sha256' => str_repeat('a', 64),
        'version_number' => 1,
    ]);
    $row = OwnRevenueImportRow::factory()->for($file, 'file')->create([
        'sheet_name' => 'ABRPRE-01',
        'row_number' => 7,
        'normalized_payload' => ['specific_item_code' => '21101'],
    ]);

    expect($budget->importSessions)->toHaveCount(1)
        ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
        ->and($file->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($row->normalized_payload['specific_item_code'])->toBe('21101');
});
```

Agregar una prueba que cree dos archivos con el mismo presupuesto, formato y `version_number=1` y espere `QueryException`.

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php
```

Expected: FAIL por tablas/clases ausentes.

- [ ] **Step 4: Implementar enums**

```php
enum OwnRevenueImportFormat: string
{
    case Abpre = 'abpre';
    case WorkSheet = 'work_sheet';
    case TechnicalSheet = 'technical_sheet';
    case Fuel = 'fuel';
    case TravelExpenses = 'travel_expenses';
}

enum OwnRevenueImportSessionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

enum OwnRevenueImportIssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}

enum OwnRevenueImportFileStatus: string
{
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case NeedsCorrection = 'needs_correction';
    case Ready = 'ready';
    case Confirmed = 'confirmed';
    case Replaced = 'replaced';
    case Discarded = 'discarded';
    case Failed = 'failed';
    case ParserPending = 'parser_pending';
}
```

- [ ] **Step 5: Implementar migraciones comunes**

```php
Schema::create('own_revenue_import_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users');
    $table->string('status')->default('open');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->index(['own_revenue_budget_id', 'status']);
});

Schema::create('own_revenue_import_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('own_revenue_import_session_id')->constrained()->cascadeOnDelete();
    $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
    $table->foreignId('uploaded_by')->constrained('users');
    $table->string('format')->nullable();
    $table->string('detected_format')->nullable();
    $table->unsignedSmallInteger('detected_year')->nullable();
    $table->string('original_name');
    $table->string('storage_disk')->default('local');
    $table->string('storage_path');
    $table->unsignedBigInteger('size_bytes');
    $table->char('sha256', 64);
    $table->unsignedInteger('version_number');
    $table->string('status')->default('uploaded');
    $table->unsignedTinyInteger('detection_confidence')->nullable();
    $table->json('detection_evidence')->nullable();
    $table->timestamp('budget_updated_at_at_analysis')->nullable();
    $table->timestamp('analyzed_at')->nullable();
    $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('confirmed_at')->nullable();
    $table->foreignId('replaced_by_file_id')->nullable()->constrained('own_revenue_import_files')->nullOnDelete();
    $table->timestamps();
    $table->unique(['own_revenue_budget_id', 'format', 'version_number'], 'own_rev_file_version_unique');
    $table->index(['own_revenue_budget_id', 'format', 'status'], 'own_rev_file_active_index');
    $table->index(['own_revenue_budget_id', 'sha256'], 'own_rev_file_hash_index');
});

Schema::create('own_revenue_import_rows', function (Blueprint $table) {
    $table->id();
    $table->foreignId('own_revenue_import_file_id')->constrained()->cascadeOnDelete();
    $table->string('sheet_name');
    $table->unsignedInteger('row_number');
    $table->string('row_kind');
    $table->char('row_hash', 64);
    $table->json('source_payload');
    $table->json('normalized_payload')->nullable();
    $table->timestamps();
    $table->unique(['own_revenue_import_file_id', 'sheet_name', 'row_number'], 'own_rev_source_row_unique');
});
```

`own_revenue_import_issues` debe enlazar archivo/fila y guardar severidad, código, campo, mensaje y contexto JSON. `own_revenue_import_decisions` debe enlazar incidencia/fila y guardar valores vigente/propuesto/resuelto JSON, resolución (`manual`, `xlsx`, `custom`), justificación, usuario y fecha.

- [ ] **Step 6: Implementar tablas ABPRE versionadas**

```php
Schema::create('own_revenue_abpre_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('own_revenue_budget_id')->constrained()->cascadeOnDelete();
    $table->foreignId('own_revenue_import_file_id')->constrained()->restrictOnDelete();
    $table->foreignId('expense_classification_id')->constrained()->restrictOnDelete();
    $table->string('responsible_unit_code');
    $table->string('responsible_unit_name');
    $table->string('budget_program_code');
    $table->string('budget_program_name');
    $table->string('component_code');
    $table->string('component_name');
    $table->string('official_activity_code');
    $table->string('official_activity_name');
    $table->string('region_code')->default('02-001');
    $table->string('region_name')->default('Felipe Carrillo Puerto');
    $table->string('specific_expense_concept_code')->nullable();
    $table->string('specific_item_code');
    $table->unsignedBigInteger('annual_amount_cents');
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index(['own_revenue_budget_id', 'specific_item_code']);
});

Schema::create('own_revenue_abpre_months', function (Blueprint $table) {
    $table->id();
    $table->foreignId('own_revenue_abpre_line_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('month');
    $table->unsignedBigInteger('amount_cents');
    $table->timestamps();
    $table->unique(['own_revenue_abpre_line_id', 'month']);
});
```

`own_revenue_abpre_justifications` debe guardar presupuesto, archivo, partida, capítulo/descripciones, programa, componente, impacto en metas y justificación. `own_revenue_import_origins` debe usar `morphs('originable')`, enlazar fila y aceptar `field_name` nullable.

- [ ] **Step 7: Implementar modelos y relaciones**

Usar `#[Fillable]`, enums en `casts()`, JSON como `array` y relaciones genéricas tipadas. Agregar a `OwnRevenueBudget`:

```php
public function importSessions(): HasMany
{
    return $this->hasMany(OwnRevenueImportSession::class);
}

public function importFiles(): HasMany
{
    return $this->hasMany(OwnRevenueImportFile::class);
}

public function abpreLines(): HasMany
{
    return $this->hasMany(OwnRevenueAbpreLine::class);
}
```

- [ ] **Step 8: GREEN, formato y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php
vendor/bin/pint --dirty --format agent
git add app/Enums/Finance/OwnRevenue/Imports app/Models/Finance/OwnRevenue/Imports database/factories/Finance/OwnRevenue/Imports database/migrations app/Models/Finance/OwnRevenue/OwnRevenueBudget.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php
git commit -m "Add own revenue import schema"
```

Expected: PASS.

## Task 3: Definir autorización

**Files:**
- Modify: `app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportAuthorizationTest.php`

- [ ] **Step 1: Crear y escribir prueba roja**

```bash
php artisan make:test --pest OwnRevenueImportAuthorizationTest --no-interaction
```

```php
it('separates import administration from consultation', function (UserRole $role, bool $view, bool $manage) {
    $user = ownRevenueImportUser($role);
    $budget = OwnRevenueBudget::factory()->create();

    expect($user->can('viewImports', $budget))->toBe($view)
        ->and($user->can('manageImports', $budget))->toBe($manage)
        ->and($user->can('confirmImports', $budget))->toBe($manage);
})->with([
    'owner' => [UserRole::Owner, true, true],
    'admin' => [UserRole::Admin, true, true],
    'manager' => [UserRole::FinanceManager, true, true],
    'assistant' => [UserRole::FinanceAssistant, true, false],
    'auditor' => [UserRole::FinanceAuditor, true, false],
]);
```

Agregar usuario inactivo/sin acceso y presupuesto no borrador; administrar/confirmar debe ser falso.

- [ ] **Step 2: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportAuthorizationTest.php
```

Expected: FAIL por métodos ausentes.

- [ ] **Step 3: Implementar policy**

```php
public function viewImports(User $user, OwnRevenueBudget $budget): bool
{
    return $this->view($user, $budget);
}

public function manageImports(User $user, OwnRevenueBudget $budget): bool
{
    return $budget->status === OwnRevenueBudgetStatus::Draft
        && $this->canAdministrate($user);
}

public function confirmImports(User $user, OwnRevenueBudget $budget): bool
{
    return $this->manageImports($user, $budget);
}
```

- [ ] **Step 4: GREEN y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportAuthorizationTest.php
vendor/bin/pint --dirty --format agent
git add app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportAuthorizationTest.php
git commit -m "Authorize own revenue imports"
```

## Task 4: Construir lector XLSX y detector

**Files:**
- Create: DTOs `XlsxCell`, `XlsxRow`, `XlsxSheet`, `XlsxWorkbook`, `WorkbookDetection` under `app/Data/Finance/OwnRevenue/Imports`
- Create: `app/Services/Finance/OwnRevenue/Imports/XlsxWorkbookReader.php`
- Create: `app/Services/Finance/OwnRevenue/Imports/OwnRevenueWorkbookFormatDetector.php`
- Test: `tests/Unit/Finance/OwnRevenue/Imports/XlsxWorkbookReaderTest.php`
- Test: `tests/Unit/Finance/OwnRevenue/Imports/OwnRevenueWorkbookFormatDetectorTest.php`
- Test fixtures: `tests/Fixtures/Finance/OwnRevenue/Imports/*.xlsx`

- [ ] **Step 1: Generar clases y pruebas**

```bash
php artisan make:class Data/Finance/OwnRevenue/Imports/XlsxCell --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/XlsxRow --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/XlsxSheet --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/XlsxWorkbook --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/WorkbookDetection --no-interaction
php artisan make:class Services/Finance/OwnRevenue/Imports/XlsxWorkbookReader --no-interaction
php artisan make:class Services/Finance/OwnRevenue/Imports/OwnRevenueWorkbookFormatDetector --no-interaction
php artisan make:test --pest XlsxWorkbookReaderTest --unit --no-interaction
php artisan make:test --pest OwnRevenueWorkbookFormatDetectorTest --unit --no-interaction
```

- [ ] **Step 2: Crear fixtures mínimos deterministas**

Usar `ZipArchive` en helpers de prueba para libros de dos filas ficticias con `workbook.xml`, relaciones, strings, fórmulas y valores cacheados. No copiar libros institucionales completos.

- [ ] **Step 3: Escribir pruebas rojas**

```php
test('reader preserves cached values formulas coordinates and sheet names', function () {
    $workbook = app(XlsxWorkbookReader::class)->read(fixtureAbpreWorkbook());
    $annual = $workbook->sheet('ABRPRE-01')->row(7)->cell('Y');

    expect($workbook->sheetNames())->toBe(['ABRPRE-01', 'Formato Justificación Partidas'])
        ->and($annual->coordinate)->toBe('Y7')
        ->and($annual->formula)->toBe('SUM(M7:X7)')
        ->and($annual->value)->toBe('1050');
});

it('detects each workbook by normalized headers', function (string $fixture, OwnRevenueImportFormat $format) {
    $detection = app(OwnRevenueWorkbookFormatDetector::class)->detect(
        app(XlsxWorkbookReader::class)->read($fixture),
    );

    expect($detection->format)->toBe($format)
        ->and($detection->confidence)->toBeGreaterThanOrEqual(80)
        ->and($detection->evidence)->not->toBeEmpty();
})->with('own revenue workbook fixtures');
```

Agregar ZIP inválido, libro ambiguo y detección de años 2026/2027.

- [ ] **Step 4: Confirmar RED**

```bash
php artisan test --compact tests/Unit/Finance/OwnRevenue/Imports/XlsxWorkbookReaderTest.php tests/Unit/Finance/OwnRevenue/Imports/OwnRevenueWorkbookFormatDetectorTest.php
```

- [ ] **Step 5: Implementar DTOs y lector**

```php
final readonly class XlsxCell
{
    public function __construct(
        public string $coordinate,
        public ?string $value,
        public ?string $formula,
    ) {}
}

final readonly class WorkbookDetection
{
    /** @param list<string> $evidence */
    public function __construct(
        public ?OwnRevenueImportFormat $format,
        public int $confidence,
        public ?int $detectedYear,
        public array $evidence,
    ) {}
}
```

`XlsxWorkbookReader::read(string $path): XlsxWorkbook` debe resolver hojas mediante relaciones, soportar strings compartidos/inline, números, vacíos, referencias dispersas, fórmulas/valores cacheados y cerrar el ZIP al fallar.

- [ ] **Step 6: Implementar detector**

Normalizar minúsculas, diacríticos, signos y espacios. Puntuar sólo firmas fuertes de Task 1; nombre del archivo no participa. Aceptar `ABRPRE-01`, `Unidad Responble` y `Ragión`.

- [ ] **Step 7: GREEN y commit**

```bash
php artisan test --compact tests/Unit/Finance/OwnRevenue/Imports/XlsxWorkbookReaderTest.php tests/Unit/Finance/OwnRevenue/Imports/OwnRevenueWorkbookFormatDetectorTest.php
vendor/bin/pint --dirty --format agent
git add app/Data/Finance/OwnRevenue/Imports app/Services/Finance/OwnRevenue/Imports tests/Unit/Finance/OwnRevenue/Imports tests/Fixtures/Finance/OwnRevenue/Imports
git commit -m "Add own revenue XLSX reader and detector"
```

## Task 5: Crear sesiones y cargas privadas versionadas

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Imports/StartOwnRevenueImportSession.php`
- Create: `app/Actions/Finance/OwnRevenue/Imports/UploadOwnRevenueImportFile.php`
- Create: `app/Actions/Finance/OwnRevenue/Imports/AssignOwnRevenueImportFormat.php`
- Create: `app/Actions/Finance/OwnRevenue/Imports/DiscardOwnRevenueImportFile.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportUploadTest.php`

- [ ] **Step 1: Generar clases y prueba**

```bash
php artisan make:class Actions/Finance/OwnRevenue/Imports/StartOwnRevenueImportSession --no-interaction
php artisan make:class Actions/Finance/OwnRevenue/Imports/UploadOwnRevenueImportFile --no-interaction
php artisan make:class Actions/Finance/OwnRevenue/Imports/AssignOwnRevenueImportFormat --no-interaction
php artisan make:class Actions/Finance/OwnRevenue/Imports/DiscardOwnRevenueImportFile --no-interaction
php artisan make:test --pest OwnRevenueImportUploadTest --no-interaction
```

- [ ] **Step 2: Escribir pruebas rojas**

```php
test('manager reuses one open session and stores an XLSX privately', function () {
    Storage::fake('local');
    $manager = ownRevenueImportUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $file = app(UploadOwnRevenueImportFile::class)->handle(
        $session,
        $manager,
        ownRevenueUploadedFile(fixtureAbpreWorkbook(), 'ABPRE 2027.xlsx'),
        false,
    );

    expect($budget->importSessions()->count())->toBe(1)
        ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
        ->and($file->version_number)->toBe(1)
        ->and($file->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($file->storage_path);
});
```

Agregar: duplicado sin force lanza `ValidationException`; con force crea versión 2; Assistant/Auditor reciben `AuthorizationException`; formato ambiguo queda `needs_correction`; formato no ABPRE queda `parser_pending`; descartar conserva archivo físico/registro y cambia un archivo no confirmado a `discarded`; un confirmado no puede descartarse.

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportUploadTest.php
```

- [ ] **Step 4: Implementar acciones**

`StartOwnRevenueImportSession` autoriza, bloquea presupuesto y reutiliza sesión abierta. Interfaz de carga:

```php
public function handle(
    OwnRevenueImportSession $session,
    User $user,
    UploadedFile $upload,
    bool $forceReanalysis,
): OwnRevenueImportFile;
```

Calcular SHA-256, detectar, asignar versión bajo `lockForUpdate()`, almacenar en `own-revenue/imports/{budget_id}/{session_id}` y borrar el físico si la transacción falla. Si el formato es ambiguo, guardar versión provisional `1`; `AssignOwnRevenueImportFormat` debe bloquear el presupuesto, recalcular el siguiente `version_number` del formato elegido y modificar sólo archivos no confirmados para evitar colisiones. `DiscardOwnRevenueImportFile` conserva evidencia y sólo cambia el estado dentro de una transacción autorizada.

- [ ] **Step 5: GREEN y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportUploadTest.php
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/OwnRevenue/Imports tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportUploadTest.php
git commit -m "Add private versioned own revenue uploads"
```

## Task 6: Analizar ABPRE y persistir staging

**Files:**
- Create: DTOs `AbpreAnalysis`, `AbpreLineData`, `AbpreJustificationData`, `ImportIssueData` under `app/Data/Finance/OwnRevenue/Imports`
- Create: `app/Services/Finance/OwnRevenue/Imports/AbpreWorkbookParser.php`
- Create: `app/Actions/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFile.php`
- Test: `tests/Unit/Finance/OwnRevenue/Imports/AbpreWorkbookParserTest.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php`

- [ ] **Step 1: Generar clases y pruebas**

```bash
php artisan make:class Data/Finance/OwnRevenue/Imports/AbpreAnalysis --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/AbpreLineData --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/AbpreJustificationData --no-interaction
php artisan make:class Data/Finance/OwnRevenue/Imports/ImportIssueData --no-interaction
php artisan make:class Services/Finance/OwnRevenue/Imports/AbpreWorkbookParser --no-interaction
php artisan make:class Actions/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFile --no-interaction
php artisan make:test --pest AbpreWorkbookParserTest --unit --no-interaction
php artisan make:test --pest AnalyzeOwnRevenueImportFileTest --no-interaction
```

- [ ] **Step 2: Escribir pruebas rojas del parser**

```php
test('ABPRE parser forward fills institutional cells and converts pesos to exact cents', function () {
    $analysis = app(AbpreWorkbookParser::class)->parse(
        app(XlsxWorkbookReader::class)->read(fixtureAbpreWorkbook()),
        ownRevenueImportBudgetData(),
        ownRevenueCogMap(['21101']),
    );

    expect($analysis->lines)->toHaveCount(2)
        ->and($analysis->lines[1]->responsibleUnitCode)->toBe('2330')
        ->and($analysis->lines[1]->specificItemCode)->toBe('21101')
        ->and($analysis->lines[1]->months[4])->toBe('105000')
        ->and($analysis->lines[1]->annualAmountCents)->toBe('105000');
});
```

Agregar casos exactos: encabezado movido; typos oficiales; `04-001` → warning `region.normalized`; año distinto → `year.mismatch`; partida ausente → error `cog.missing_item`; anual distinto → `abpre.annual_mismatch`; importe inválido → `amount.invalid`; otra UR → info `abpre.other_unit`; justificación ausente → `abpre.missing_justification`; agrupación tras normalizar región.

- [ ] **Step 3: Escribir prueba roja de análisis sin efectos**

```php
test('analysis replaces staging and never creates confirmed ABPRE lines', function () {
    $file = ownRevenueUploadedAbpreFile();

    app(AnalyzeOwnRevenueImportFile::class)->handle($file, $file->uploadedBy);

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($file->rows()->count())->toBeGreaterThan(0)
        ->and($file->issues()->where('severity', 'error')->count())->toBe(0)
        ->and(OwnRevenueAbpreLine::query()->count())->toBe(0);
});
```

Agregar archivo con error → `needs_correction`; reanálisis reemplaza sólo filas/incidencias del mismo archivo.

- [ ] **Step 4: Confirmar RED**

```bash
php artisan test --compact tests/Unit/Finance/OwnRevenue/Imports/AbpreWorkbookParserTest.php tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php
```

- [ ] **Step 5: Implementar conversión monetaria exacta**

```php
private function pesosToCents(?string $value): ?string;
```

Aceptar no negativos con máximo dos decimales, separar entero/decimal con regex, rellenar dos dígitos y validar mediante `UnsignedBigInteger`. No usar `float`, `round()`, `parseFloat` ni dependencia decimal nueva.

- [ ] **Step 6: Implementar parser por encabezados**

Buscar hojas por encabezados normalizados, no por número. Forward-fill A–J sólo si la fila tiene partida. Agrupar por:

```text
UR + programa + componente + actividad oficial + concepto específico + partida + región normalizada
```

Sumar meses como enteros decimales de cadena; conservar todas las filas fuente y mapear justificaciones por partida.

- [ ] **Step 7: Implementar acción de análisis**

Autorizar `manageImports`, bloquear archivo/presupuesto, verificar hash, cambiar a `analyzing`, parsear, reemplazar staging y guardar `budget_updated_at_at_analysis`. Errores → `needs_correction`; éxito → `ready`; excepción inesperada → `failed` con incidencia legible.

- [ ] **Step 8: GREEN y commit**

```bash
php artisan test --compact tests/Unit/Finance/OwnRevenue/Imports/AbpreWorkbookParserTest.php tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php
vendor/bin/pint --dirty --format agent
git add app/Data/Finance/OwnRevenue/Imports app/Services/Finance/OwnRevenue/Imports/AbpreWorkbookParser.php app/Actions/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFile.php tests/Unit/Finance/OwnRevenue/Imports tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php
git commit -m "Analyze own revenue ABPRE workbooks"
```

## Task 7: Confirmar versión ABPRE atómica

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImport.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImportTest.php`

- [ ] **Step 1: Generar y escribir prueba roja**

```bash
php artisan make:class Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImport --no-interaction
php artisan make:test --pest ConfirmOwnRevenueAbpreImportTest --no-interaction
```

```php
test('confirmation creates immutable ABPRE lines months justifications and origins', function () {
    $file = ownRevenueAnalyzedAbpreFile(OwnRevenueImportFileStatus::Ready);

    app(ConfirmOwnRevenueAbpreImport::class)->handle($file, $file->uploadedBy, []);

    $line = OwnRevenueAbpreLine::query()->sole();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($line->months)->toHaveCount(12)
        ->and($line->origins)->not->toBeEmpty()
        ->and($line->annual_amount_cents)->toBe('105000')
        ->and(OwnRevenueAbpreJustification::query()->count())->toBe(1);
});
```

Agregar: 403 antes de mutar; estados/formato inválidos; presupuesto modificado después del análisis; warning sin aceptación; idempotencia; versión 1 pasa a `replaced` sin borrar líneas; rollback al fallar meses; concurrencia deja un ABPRE vigente.

- [ ] **Step 2: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImportTest.php
```

- [ ] **Step 3: Implementar acción**

```php
/** @param array<int, array{issue_id:int, resolution:string, resolved_value:mixed, justification:?string}> $decisions */
public function handle(
    OwnRevenueImportFile $file,
    User $user,
    array $decisions,
): OwnRevenueImportFile;
```

Dentro de `DB::transaction($callback, attempts: 3)`: autorizar, bloquear presupuesto/archivo, revalidar estado/hash/timestamp, validar decisiones, crear líneas/meses/justificaciones/orígenes, reemplazar el confirmado anterior y confirmar el nuevo. Nunca borrar versiones previas. Requerir aceptación para `year.mismatch`, `region.normalized`, `abpre.annual_mismatch` y `abpre.missing_justification`.

- [ ] **Step 4: GREEN y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImportTest.php
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImport.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImportTest.php
git commit -m "Confirm versioned ABPRE imports"
```

## Task 8: Exponer endpoints y DTOs Inertia

**Files:**
- Create: controllers `OwnRevenueImportController`, `OwnRevenueImportFileController`, `OwnRevenueImportAnalysisController`, `OwnRevenueAbpreConfirmationController` under `app/Http/Controllers/Finance`
- Create: requests under `app/Http/Requests/Finance/OwnRevenue/Imports`
- Modify: `app/Http/Controllers/Finance/OwnRevenueBudgetController.php`
- Modify: `app/Http/Requests/Finance/OwnRevenue/StoreOwnRevenueBudgetRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php`

- [ ] **Step 1: Generar clases**

```bash
php artisan make:controller Finance/OwnRevenueImportController --no-interaction
php artisan make:controller Finance/OwnRevenueImportFileController --no-interaction
php artisan make:controller Finance/OwnRevenueImportAnalysisController --invokable --no-interaction
php artisan make:controller Finance/OwnRevenueAbpreConfirmationController --invokable --no-interaction
php artisan make:request Finance/OwnRevenue/Imports/StoreOwnRevenueImportFileRequest --no-interaction
php artisan make:request Finance/OwnRevenue/Imports/UpdateOwnRevenueImportFileFormatRequest --no-interaction
php artisan make:request Finance/OwnRevenue/Imports/ConfirmOwnRevenueAbpreImportRequest --no-interaction
php artisan make:test --pest OwnRevenueImportManagementTest --no-interaction
```

- [ ] **Step 2: Escribir pruebas HTTP rojas**

Probar rutas/métodos/nombres:

```text
GET  finance.own-revenue.budgets.imports.show
POST finance.own-revenue.budgets.imports.files.store
PUT  finance.own-revenue.budgets.imports.files.format.update
POST finance.own-revenue.budgets.imports.files.analyze
POST finance.own-revenue.budgets.imports.files.abpre.confirm
GET  finance.own-revenue.budgets.imports.files.download
DELETE finance.own-revenue.budgets.imports.files.discard
```

Probar `creation_mode=import`: crea presupuesto/sesión atómicamente y redirige a `imports.show`; `source_budget_id` queda prohibido.

- [ ] **Step 3: Probar validación y permisos**

```php
return [
    'file' => ['required', File::types(['xlsx'])->max(20 * 1024)],
    'force_reanalysis' => ['sometimes', 'boolean'],
];
```

Probar 403 de mutaciones para Assistant/Auditor, 200 en consulta/descarga, descarte sólo de archivos no confirmados y 404 si archivo/presupuesto no coinciden.

- [ ] **Step 4: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php
```

- [ ] **Step 5: Implementar controladores delgados y rutas**

Autorizar, validar, llamar acciones y transformar DTOs. Descargar con `Storage::disk($file->storage_disk)->download($file->storage_path, $file->original_name)` sin exponer `storage_path`. DTO: cinco slots, versiones, conteos por severidad, preview paginado, permisos e importes string.

- [ ] **Step 6: Extender creación**

```php
'creation_mode' => ['sometimes', Rule::in(['blank', 'copy', 'import'])],
```

`import` reutiliza `InitializeOwnRevenueBudget`, permite valores anuales pendientes, inicia sesión y redirige al asistente; conservar contratos `blank`/`copy`.

- [ ] **Step 7: GREEN, Wayfinder y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php
vendor/bin/pint --dirty --format agent
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run build
git add app/Http/Controllers/Finance app/Http/Requests/Finance/OwnRevenue routes/web.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php resources/js/actions resources/js/routes
git commit -m "Expose own revenue import endpoints"
```

## Task 9: Construir modalidad XLSX y asistente

**Files:**
- Modify: `resources/js/pages/finance/own-revenue/budgets/create.tsx`
- Modify: `resources/js/pages/finance/own-revenue/budgets/show.tsx`
- Create: `resources/js/pages/finance/own-revenue/imports/show.tsx`
- Create: components under `resources/js/components/finance/own-revenue/imports`
- Create: `resources/js/types/finance-own-revenue-imports.ts`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php`

- [ ] **Step 1: Escribir prueba roja**

```php
test('workspace exposes five slots exact string amounts and permissions', function () {
    $manager = ownRevenueImportUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/imports/show', false)
            ->has('slots', 5)
            ->where('slots.0.format', 'abpre')
            ->where('permissions.upload', true)
            ->where('permissions.confirm', true));
});
```

Agregar Assistant (`upload=false`, `confirm=false`, `download=true`), modo import en creación y prueba fuente sin conversiones numéricas binarias.

- [ ] **Step 2: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
```

- [ ] **Step 3: Agregar tercera modalidad**

```ts
type Mode = 'blank' | 'copy' | 'import';

type CreateBudgetFormData = {
    creation_mode: Mode;
    source_budget_id: string;
    fiscal_year: string;
    institution_name: string;
    responsible_unit_code: string;
    responsible_unit_name: string;
    budget_program_code: string;
    budget_program_name: string;
    component_code: string;
    component_name: string;
    official_activity_code: string;
    official_activity_name: string;
};
```

Mostrar `En blanco`, `Copiar ejercicio`, `Desde archivos XLSX`. En importación pedir año/fotografía institucional; UMA, combustible, ingreso y recorte pueden quedar pendientes.

- [ ] **Step 4: Construir cinco slots**

```ts
type ImportFileSlotProps = {
    format: OwnRevenueImportFormat;
    label: string;
    versions: OwnRevenueImportFileSummary[];
    canManage: boolean;
    onUpload: (file: File, forceReanalysis: boolean) => void;
};
```

Usar POST multipart de Inertia y `progress.percentage`; aceptar `.xlsx`, selección/drag-drop, mostrar nombre/tamaño/estado/versiones, corregir tipo y descartar versiones no confirmadas. No leer XLSX en navegador.

- [ ] **Step 5: Incidencias y preview ABPRE**

Crear `import-issue-list.tsx` y `abpre-preview.tsx`. Preview: partida, actividad, región original/normalizada, enero–diciembre y anual. Convertir centavos string a visualización con operaciones de cadena. Confirmación envía decisiones para warnings requeridos.

- [ ] **Step 6: Integrar tablero**

Tarjeta `Importaciones XLSX` con confirmados/faltantes/pendientes de analizador y enlace. Ocultar mutaciones según permisos.

- [ ] **Step 7: GREEN frontend y commit**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run types:check
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run lint:check
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run build
git add resources/js/pages/finance/own-revenue resources/js/components/finance/own-revenue/imports resources/js/types/finance-own-revenue-imports.ts tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
git commit -m "Add own revenue XLSX import workspace"
```

## Task 10: Verificar archivos reales y regresión

**Files:**
- Modify only if a verified defect requires it: files from Tasks 2–9
- Test: `tests/Feature/Finance/OwnRevenue/Imports/*`
- Test: `tests/Unit/Finance/OwnRevenue/Imports/*`

- [ ] **Step 1: Probar archivos reales sin incorporarlos al repo**

Validar ambos ABPRE; combustible, viáticos, ficha técnica y hoja de trabajo reales. Expected: ABPRE detectados/analizados; otros cuatro detectados `parser_pending`; ejemplo 2027 reporta UR/regiones ajenas sin confirmar líneas.

- [ ] **Step 2: Suites específicas**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports tests/Unit/Finance/OwnRevenue/Imports
```

- [ ] **Step 3: Formato y suite completa**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

- [ ] **Step 4: Frontend secuencial**

```bash
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run types:check
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run lint:check
PATH='/Users/willix/Library/Application Support/Herd/config/nvm/versions/node/v22.23.1/bin':$PATH npm run build:ssr
```

No ejecutar Pest simultáneamente con Vite: ambos consumen `public/build`.

- [ ] **Step 5: Navegador con base aislada**

Resolver URL con Boost y enlazar el worktree en Herd. Manager: crear desde XLSX, cargar ABPRE con año distinto, revisar/aceptar warnings, confirmar versión 1, reemplazar por versión 2, cargar otros cuatro formatos. Assistant: consultar/descargar sin mutaciones. Revisar overlays, respuestas HTTP y `browser-logs`.

- [ ] **Step 6: Estado final**

```bash
git diff --check
git status --short --branch
```

Si verificación corrige código:

```bash
git add app resources/js routes tests database
git commit -m "Verify own revenue ABPRE import flow"
```

No crear commit vacío.

## Criterios de aceptación

- Se crea un ejercicio mediante `Desde archivos XLSX` y puede continuarse después.
- Hay exactamente cinco slots independientes.
- Todos los formatos reales se detectan por contenido; ambigüedad exige corrección.
- Archivos privados, versionados y descargables sólo con autorización.
- Duplicado exige reanálisis explícito.
- ABPRE analiza sin efectos y conserva hoja/fila.
- Año del ejercicio no cambia por XLSX.
- Región se normaliza a `02-001` con warning.
- COG ausente bloquea.
- Centavos exactos y anual conciliado con doce meses.
- Confirmación crea versión ABPRE inmutable con meses, justificaciones y orígenes.
- Reemplazo conserva versión anterior.
- Manager/Admin/Owner administran; Assistant/Auditor consultan.
- Pest, Pint, TypeScript, ESLint, SSR y navegador pasan.

## Planes posteriores

1. Fase 3B: hoja de trabajo y conciliación actividad/mes.
2. Fase 3C: ficha técnica y necesidades generales.
3. Fase 3D: combustible, recorridos y redondeo al múltiplo de $50.
4. Fase 3E: viáticos, zonas, UMA, alimentación y hospedaje.
5. Fase 3F: conciliación transversal y preparación del presupuesto inicial.
