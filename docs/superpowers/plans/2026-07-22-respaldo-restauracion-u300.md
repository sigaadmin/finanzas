# Respaldo y restauración de U300 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear paquetes ZIP verificables de un ejercicio U300 y restaurarlos de forma atómica, con respaldo preventivo y bitácora inmutable.

**Architecture:** Un servicio de archivo serializa el grafo U300, sus referencias COG y archivos físicos en un ZIP privado con manifiesto y hashes. Un validador crea una vista previa almacenada temporalmente; el restaurador vuelve a validar, genera un respaldo preventivo, bloquea el ejercicio, reconstruye el grafo con IDs nuevos y registra el resultado. El índice U300 expone descarga, restauración con confirmación y la bitácora.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, `ZipArchive`, storage local/público de Laravel, Inertia React 3, Wayfinder, Pest 4 y Tailwind 4.

---

## Estructura de archivos

- `app/Models/Finance/U300/U300BackupArchive.php`: metadatos del ZIP preservado, incluidos respaldo manual y preventivo.
- `app/Models/Finance/U300/U300BackupOperation.php`: bitácora inmutable de cada generación, validación y restauración.
- `app/Actions/Finance/U300/CreateU300BackupArchive.php`: serializa y almacena un ZIP desde un ejercicio U300.
- `app/Actions/Finance/U300/InspectU300BackupArchive.php`: valida un ZIP cargado, sus hashes y referencias COG, y guarda la vista previa temporal.
- `app/Actions/Finance/U300/RestoreU300BackupArchive.php`: crea el preventivo y sustituye el ejercicio dentro de una transacción.
- `app/Services/Finance/U300/U300BackupPayload.php`: construye y valida el manifiesto versionado, sin IDs de la instalación.
- `app/Services/Finance/U300/U300BackupFilePaths.php`: localiza, empaqueta y restaura PDF/fotos sin aceptar rutas inseguras.
- `app/Http/Controllers/Finance/U300BackupController.php`: descarga, vista previa y confirmación de restauración.
- `app/Http/Requests/Finance/PreviewU300BackupRestoreRequest.php` y `RestoreU300BackupRequest.php`: autorización y validación HTTP.
- `database/migrations/*_create_u300_backup_archives_table.php` y `*_create_u300_backup_operations_table.php`: persistencia e índices de auditoría.
- `resources/js/pages/finance/u300/programs/index.tsx`: acciones, diálogo de carga/vista previa/confirmación y tabla de bitácora.
- `tests/Feature/Finance/U300BackupRestoreTest.php`: contrato HTTP, paquete, seguridad, sustitución, COG y auditoría.
- `tests/Unit/Finance/U300/U300BackupPayloadTest.php`: estructura determinista y validaciones de manifiesto.

### Task 1: Persistencia, autorización y contratos de auditoría

**Files:**
- Create: `database/migrations/2026_07_22_000001_create_u300_backup_archives_table.php`
- Create: `database/migrations/2026_07_22_000002_create_u300_backup_operations_table.php`
- Create: `app/Models/Finance/U300/U300BackupArchive.php`
- Create: `app/Models/Finance/U300/U300BackupOperation.php`
- Create: `database/factories/Finance/U300/U300BackupArchiveFactory.php`
- Create: `database/factories/Finance/U300/U300BackupOperationFactory.php`
- Modify: `app/Models/User.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Finance/U300BackupRestoreTest.php`

- [ ] **Step 1: Escribir las pruebas de permiso y bitácora que fallen.**

```php
test('only an owner, admin, or finance manager may generate or restore U300 backups', function () {
    $assistant = u300BackupUser(UserRole::FinanceAssistant);
    $program = u300BackupProgram($assistant, 2026);

    $this->actingAs($assistant)
        ->get(route('finance.u300.programs.backups.download', $program))
        ->assertForbidden();

    $this->actingAs($assistant)
        ->post(route('finance.u300.backups.preview'), ['archive' => UploadedFile::fake()->create('u300.zip')])
        ->assertForbidden();
});

test('backup archive records retain their actor, checksum and immutable operation log', function () {
    $archive = U300BackupArchive::factory()->create();
    $operation = U300BackupOperation::factory()->for($archive)->create();

    expect($archive->sha256)->toHaveLength(64)
        ->and($operation->status)->toBe('succeeded');
});
```

- [ ] **Step 2: Ejecutar el archivo para comprobar que falla.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php`

Expected: FAIL porque no existen los modelos, rutas ni factories.

- [ ] **Step 3: Crear las migraciones y modelos mínimos.**

`u300_backup_archives` contiene `fiscal_year`, `kind` (`manual` o `pre_restore`), `disk`, `path`, `original_filename`, `size_bytes`, `sha256`, `manifest` JSON, `created_by` nullable con `nullOnDelete`, timestamps y los índices `['fiscal_year', 'created_at']` y `sha256` único. `u300_backup_operations` contiene `u300_backup_archive_id` nullable con `nullOnDelete`, `fiscal_year`, `type` (`generated`, `restore_previewed`, `restored`), `status` (`pending`, `succeeded`, `failed`, `cancelled`), `performed_by` nullable con `nullOnDelete`, `details` JSON nullable, `failure_reason` nullable y timestamps; no se añade `updated_at` a este último para que las entradas no se editen.

Los modelos usan `#[Fillable(...)]`, relaciones `belongsTo` tipadas, y casts `manifest/details => array`. En `User`, agregar métodos explícitos:

```php
public function canManageU300Backups(): bool
{
    return $this->authorizedAccess?->is_active === true
        && in_array($this->authorizedAccess->role, [
            UserRole::Owner,
            UserRole::Admin,
            UserRole::FinanceManager,
        ], true);
}
```

Definir `manage-u300-backups` en `AppServiceProvider` mediante ese método. No crear una política por modelo: las rutas U300 actuales usan gates de capacidad.

- [ ] **Step 4: Ejecutar migraciones y pruebas.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php`

Expected: PASS para los permisos y la persistencia de archivo/operación.

- [ ] **Step 5: Formatear y confirmar.**

Run: `vendor/bin/pint --dirty --format agent && git add app/Models/User.php app/Models/Finance/U300/U300BackupArchive.php app/Models/Finance/U300/U300BackupOperation.php app/Providers/AppServiceProvider.php database/migrations database/factories/Finance/U300 tests/Feature/Finance/U300BackupRestoreTest.php && git commit -m "feat: add U300 backup audit records"`

### Task 2: Construir el manifiesto y el ZIP autosuficiente

**Files:**
- Create: `app/Services/Finance/U300/U300BackupPayload.php`
- Create: `app/Services/Finance/U300/U300BackupFilePaths.php`
- Create: `app/Actions/Finance/U300/CreateU300BackupArchive.php`
- Test: `tests/Unit/Finance/U300/U300BackupPayloadTest.php`
- Test: `tests/Feature/Finance/U300BackupRestoreTest.php`

- [ ] **Step 1: Escribir pruebas de contenido completo.**

```php
test('a U300 archive contains the complete graph, COG snapshots and referenced files', function () {
    Storage::fake('local');
    Storage::fake('public');
    [$user, $program] = u300BackupProgramWithVerdictAdjustmentExecutionAndPhotos(2026);

    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $zip = new ZipArchive();
    $zip->open(Storage::disk('local')->path($archive->path));

    expect($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('data/program.json'))->not->toBeFalse()
        ->and($zip->locateName('files/source/proyecto.pdf'))->not->toBeFalse()
        ->and($zip->locateName('files/technical-sheets/'))->not->toBeFalse();
});
```

- [ ] **Step 2: Ejecutar la prueba para comprobar el fallo.**

Run: `php artisan test --compact tests/Unit/Finance/U300/U300BackupPayloadTest.php --filter=complete`

Expected: FAIL porque no existe el exportador.

- [ ] **Step 3: Implementar un payload con claves portables.**

`U300BackupPayload::fromPrograms(Collection $programs)` debe cargar de una vez:

```php
[
    'budgetVersions.requestedItems',
    'budgetVersions.budgetLines.expenseClassification',
    'budgetVersions.budgetLines.technicalSheet',
    'budgetVersions.budgetLines.movements',
    'projects.goals.actions',
]
```

Asignar una clave UUID por cada programa, versión, proyecto, meta, acción, solicitud, partida, ficha y movimiento. Serializar atributos de negocio y claves UUID de sus padres, nunca los IDs de usuarios ni IDs de BD. Para `imported_by`, `created_by`, `recorded_by` y `cancelled_by`, guardar sólo el correo normalizado cuando exista; al restaurar se usa el usuario que ejecuta la restauración si aquel correo no existe o no está autorizado.

Para cada `expenseClassification`, serializar la instantánea completa y usar la clave `{fiscal_year}:{specific_item_code}`; una partida sin COG conserva `cog_key: null`. Convertir cada ruta de foto permitida y el `source_path` en entradas de `files/`; usar hash SHA-256 como nombre de archivo interno y registrar en el payload cómo reescribir cada ruta lógica.

`CreateU300BackupArchive` crea `manifest.json` versión `1`, `data/program.json` y los binarios con `ZipArchive`, calcula los hashes desde el contenido ya escrito y guarda el ZIP en el disco `local` bajo `u300/backups/{uuid}.zip`. Debe crear la fila de archivo y la operación `generated` con conteos por entidad; si falla, borrar el ZIP parcial y registrar una operación `failed` sin exponer rutas internas.

- [ ] **Step 4: Ejecutar pruebas de exportación y regresión U300.**

Run: `php artisan test --compact tests/Unit/Finance/U300/U300BackupPayloadTest.php tests/Feature/Finance/U300BackupRestoreTest.php --filter=archive`

Expected: PASS; manifest, JSON, PDF y fotos están en el ZIP y cada hash coincide.

- [ ] **Step 5: Formatear y confirmar.**

Run: `vendor/bin/pint --dirty --format agent && git add app/Services/Finance/U300 app/Actions/Finance/U300/CreateU300BackupArchive.php tests/Unit/Finance/U300/U300BackupPayloadTest.php tests/Feature/Finance/U300/U300BackupRestoreTest.php && git commit -m "feat: export complete U300 backup archives"`

### Task 3: Validar ZIP y preparar una vista previa segura

**Files:**
- Create: `app/Http/Requests/Finance/PreviewU300BackupRestoreRequest.php`
- Create: `app/Actions/Finance/U300/InspectU300BackupArchive.php`
- Create: `app/Http/Controllers/Finance/U300BackupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/U300BackupRestoreTest.php`

- [ ] **Step 1: Escribir pruebas de inspección fallidas.**

```php
test('a restore preview rejects a tampered archive before any U300 data changes', function () {
    [$user, $program] = u300BackupProgramWithVerdictAdjustmentExecutionAndPhotos(2026);
    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $tampered = replaceManifestHash(Storage::disk('local')->path($archive->path));

    $this->actingAs($user)
        ->post(route('finance.u300.backups.preview'), ['archive' => new UploadedFile($tampered, 'u300.zip', 'application/zip', null, true)])
        ->assertSessionHasErrors('archive');

    expect(U300Program::where('fiscal_year', 2026)->count())->toBe(1);
});

test('a restore preview rejects a package whose COG snapshot is unavailable or different', function () {
    // El ZIP válido declara 2026:37501; se elimina o altera esa clasificación antes de previsualizar.
});
```

- [ ] **Step 2: Ejecutar las pruebas de inspección.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php --filter=preview`

Expected: FAIL porque no hay endpoint ni inspector.

- [ ] **Step 3: Implementar solicitud, inspector y endpoints de sólo vista previa.**

La solicitud autoriza `manage-u300-backups` y valida un único campo `archive` con `required`, `file`, `mimetypes:application/zip,application/x-zip-compressed` y un máximo explícito de 512 MB. `InspectU300BackupArchive` guarda el upload en `u300/restore-previews/{uuid}.zip`, abre sólo entradas bajo `manifest.json`, `data/program.json`, `files/source/` y `files/technical-sheets/`, rechaza nombres absolutos, `..`, archivos duplicados, entradas mayores al límite del manifiesto y hashes incorrectos.

Verificar que el manifiesto sea versión 1, que `fiscal_year` sea entero y coincida con cada programa, que todas las claves UUID existan exactamente una vez, y que los conteos declarados coincidan. Para cada COG, buscar por `fiscal_year` y `specific_item_code`, y comparar todos los campos del snapshot; cualquier diferencia devuelve un error de validación sin crear/modificar U300.

El controlador responde a `POST finance/u300/backups/preview` con redirect al índice, guardando en sesión un token, SHA-256 del ZIP temporal, año, conteos y una lista legible de advertencias. Registrar `restore_previewed` con resultado `succeeded` o `failed`. Definir también `GET finance/u300/programs/{program}/backups/download` para crear el paquete y descargarlo como `u300-{fiscal_year}-{Ymd-His}.zip`; esta ruta requiere el mismo gate.

- [ ] **Step 4: Ejecutar pruebas HTTP.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php --filter=preview`

Expected: PASS; un paquete correcto devuelve la vista previa y ninguno inválido altera registros.

- [ ] **Step 5: Formatear y confirmar.**

Run: `vendor/bin/pint --dirty --format agent && git add app/Http/Requests/Finance/PreviewU300BackupRestoreRequest.php app/Actions/Finance/U300/InspectU300BackupArchive.php app/Http/Controllers/Finance/U300BackupController.php routes/web.php tests/Feature/Finance/U300BackupRestoreTest.php && git commit -m "feat: validate U300 backup restore previews"`

### Task 4: Restaurar un ejercicio con respaldo preventivo y reversión

**Files:**
- Create: `app/Http/Requests/Finance/RestoreU300BackupRequest.php`
- Create: `app/Actions/Finance/U300/RestoreU300BackupArchive.php`
- Modify: `app/Actions/Finance/U300/CreateU300BackupArchive.php`
- Modify: `app/Http/Controllers/Finance/U300BackupController.php`
- Test: `tests/Feature/Finance/U300BackupRestoreTest.php`

- [ ] **Step 1: Escribir las pruebas de sustitución y rollback.**

```php
test('restoring a 2026 package replaces only U300 2026 and creates a preventive archive', function () {
    [$user, $source2026] = u300BackupProgramWithVerdictAdjustmentExecutionAndPhotos(2026);
    $package = app(CreateU300BackupArchive::class)->handle($source2026, $user, 'manual');
    $current2026 = u300BackupProgram($user, 2026, 'Datos que deben sustituirse');
    $otherYear = u300BackupProgram($user, 2027, 'Datos que deben conservarse');

    $preview = previewU300Archive($this, $user, $package);
    $this->actingAs($user)->post(route('finance.u300.backups.restore'), [
        'preview_token' => $preview->token,
        'confirmation' => 'RESTAURAR U300 2026',
    ])->assertRedirect(route('finance.u300.programs.index'));

    expect(U300Program::where('fiscal_year', 2026)->pluck('name'))->toContain($source2026->name)
        ->and(U300Program::find($otherYear->id))->not->toBeNull();
    expect(U300BackupArchive::where('kind', 'pre_restore')->where('fiscal_year', 2026)->exists())->toBeTrue();
});

test('a persistence failure leaves the current fiscal year untouched and records a failed restoration', function () {
    // Simular excepción en la creación de una ficha después de crear las filas padre.
});
```

- [ ] **Step 2: Ejecutar las pruebas de restauración.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php --filter=restor`

Expected: FAIL porque la confirmación y el restaurador no existen.

- [ ] **Step 3: Implementar restauración en dos fases.**

La solicitud exige `preview_token` presente en sesión y `confirmation` exactamente igual a `RESTAURAR U300 {fiscal_year}`. El restaurador vuelve a calcular el hash del archivo temporal y vuelve a ejecutar la validación completa; no confía en la vista previa de sesión por sí sola.

Antes de abrir la transacción, extraer los binarios permitidos a una carpeta temporal y copiar las fotos a nuevas rutas únicas en el disco `public`; si esto falla, eliminar sólo esas rutas nuevas. Luego crear `U300BackupOperation` pendiente y generar con `CreateU300BackupArchive` un paquete `pre_restore` de todos los `U300Program` del ejercicio actual. Si no existe U300 para ese año, continuar sin preventivo y anotar `previous_programs_count: 0`.

Dentro de `DB::transaction`, obtener con `lockForUpdate()` todos los programas del ejercicio, eliminar sus grafos mediante los `cascadeOnDelete` existentes, y reconstruir en orden: programa, versiones, proyectos, metas, acciones, solicitudes, partidas, fichas y movimientos. Mantener mapas `old_uuid => new_id`; resolver cada COG por `fiscal_year + specific_item_code`; asignar el usuario restaurador a auditorías cuyo usuario exportado no esté disponible. Reescribir en `goods_profile` y en el patrón legado de `technical_specs` las rutas de foto a sus nuevas rutas públicas. Al confirmar, eliminar PDF/fotos antiguos sólo si ya no están referidos por otro programa; al capturar una excepción, borrar únicamente los archivos nuevos, dejar la base de datos intacta y actualizar la operación como `failed` fuera de la transacción.

El controlador elimina la sesión y ZIP temporal solamente después de éxito o de un rechazo definitivo, usa `Inertia::flash` para el resultado y nunca incluye la causa técnica del error en la respuesta del navegador.

- [ ] **Step 4: Ejecutar la suite dirigida de restauración.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php --filter=restor`

Expected: PASS; 2026 queda sustituido, 2027 no cambia, el preventivo existe y la prueba de excepción revierte todo.

- [ ] **Step 5: Formatear y confirmar.**

Run: `vendor/bin/pint --dirty --format agent && git add app/Http/Requests/Finance/RestoreU300BackupRequest.php app/Actions/Finance/U300/RestoreU300BackupArchive.php app/Actions/Finance/U300/CreateU300BackupArchive.php app/Http/Controllers/Finance/U300BackupController.php tests/Feature/Finance/U300BackupRestoreTest.php && git commit -m "feat: restore U300 backups by fiscal year"`

### Task 5: Exponer respaldo, vista previa y bitácora en el índice Inertia

**Files:**
- Modify: `app/Http/Controllers/Finance/U300ProgramController.php`
- Modify: `resources/js/pages/finance/u300/programs/index.tsx`
- Modify: `resources/js/routes/finance/u300/index.ts` (generado por Wayfinder si el proyecto lo requiere)
- Test: `tests/Feature/Finance/U300ProgramIndexTest.php`
- Test: `tests/Frontend/u300-backup-restore.test.mjs`

- [ ] **Step 1: Escribir pruebas de props y estado del diálogo.**

```php
test('the U300 index exposes backup history only to users who may manage it', function () {
    $user = u300ProgramIndexUser();
    U300BackupOperation::factory()->for($user, 'performedBy')->create(['fiscal_year' => 2026]);

    $this->actingAs($user)->get(route('finance.u300.programs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage_backups', true)
            ->has('backup_operations', 1));
});
```

```js
import { canConfirmU300Restore } from '../../resources/js/pages/finance/u300/programs/backup-restore-state.js';

test('restore confirmation requires the exact fiscal-year phrase', () => {
    assert.equal(canConfirmU300Restore('RESTAURAR U300 2026', 2026, false), true);
    assert.equal(canConfirmU300Restore('RESTAURAR U300 2027', 2026, false), false);
});
```

- [ ] **Step 2: Ejecutar pruebas antes de la interfaz.**

Run: `php artisan test --compact tests/Feature/Finance/U300ProgramIndexTest.php && node --test tests/Frontend/u300-backup-restore.test.mjs`

Expected: FAIL porque no se comparten props ni existe el helper de confirmación.

- [ ] **Step 3: Implementar la UI con rutas Wayfinder.**

Extender `index()` con las últimas 30 operaciones, ordenadas por creación descendente, y los campos mínimos de archivo/operación. En la tabla de cada programa, mostrar **Descargar respaldo** sólo cuando `can_manage_backups` sea verdadero y enlazar mediante la función Wayfinder de descarga; no usar URLs literales.

Agregar al encabezado un diálogo **Restaurar respaldo** que use `useForm({ archive: null })` para cargar ZIP por `POST` a la ruta de vista previa y muestre `progress`. Después de la redirección, leer la prop `restore_preview`, mostrar año/conteos/advertencias y pedir la frase exacta; un segundo `useForm` envía `preview_token` y `confirmation` al endpoint de restauración. Reutilizar `Dialog`, `Input`, `InputError`, `Button` y el patrón de `local-data-reset-card.tsx`; usar variante `destructive` para confirmar y deshabilitar mientras `processing` sea verdadero.

Al final, añadir una sección **Bitácora de respaldos** que muestre fecha, usuario, tipo, ejercicio, nombre/tamaño/hash abreviado y estado. Los errores de validación aparecen junto al input; la bitácora no expone paths de almacenamiento ni excepciones.

- [ ] **Step 4: Regenerar Wayfinder y ejecutar verificaciones de frontend.**

Run: `php artisan wayfinder:generate --no-interaction && php artisan test --compact tests/Feature/Finance/U300ProgramIndexTest.php && node --test tests/Frontend/u300-backup-restore.test.mjs && npm run lint`

Expected: PASS; imports tipados resuelven y la frase de restauración no puede omitirse.

- [ ] **Step 5: Confirmar la interfaz.**

Run: `git add app/Http/Controllers/Finance/U300ProgramController.php resources/js/pages/finance/u300/programs/index.tsx resources/js/pages/finance/u300/programs/backup-restore-state.js resources/js/routes/finance/u300 tests/Feature/Finance/U300ProgramIndexTest.php tests/Frontend/u300-backup-restore.test.mjs && git commit -m "feat: add U300 backup and restore controls"`

### Task 6: Verificación integrada y límites de seguridad

**Files:**
- Modify: `tests/Feature/Finance/U300BackupRestoreTest.php`
- Modify: `tests/Unit/Finance/U300/U300BackupPayloadTest.php`

- [ ] **Step 1: Añadir casos de borde de seguridad.**

Cubrir explícitamente un ZIP con `../`, una entrada extra no permitida, PDF/foto faltante, hash incorrecto, manifest de versión futura, ejercicio distinto entre manifest y datos, UUID duplicado, COG nulo válido, COG faltante, confirmación errónea, operador sin permiso y una restauración repetida del mismo paquete.

- [ ] **Step 2: Ejecutar las pruebas de U300 relevantes.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php tests/Unit/Finance/U300/U300BackupPayloadTest.php tests/Feature/Finance/U300TechnicalSheetTest.php tests/Feature/Finance/U300CogConversionTest.php tests/Feature/Finance/U300BudgetExecutionTest.php`

Expected: PASS; el flujo nuevo conserva las reglas existentes de fotos, COG y ejecución.

- [ ] **Step 3: Formatear, comprobar cambios y confirmar.**

Run: `vendor/bin/pint --dirty --format agent && git diff --check && git status --short && git add tests/Feature/Finance/U300BackupRestoreTest.php tests/Unit/Finance/U300/U300BackupPayloadTest.php && git commit -m "test: cover U300 backup restore integrity"`

- [ ] **Step 4: Realizar la verificación final de calidad.**

Run: `php artisan test --compact tests/Feature/Finance/U300BackupRestoreTest.php tests/Unit/Finance/U300/U300BackupPayloadTest.php tests/Feature/Finance/U300ProgramIndexTest.php && npm run lint && git status --short`

Expected: todas las pruebas y lint PASS, y el árbol de trabajo no contiene cambios no confirmados.
