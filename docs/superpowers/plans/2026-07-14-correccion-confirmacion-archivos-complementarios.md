# Corrección de confirmación de archivos complementarios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir los cuatro hallazgos altos de la auditoría para que los archivos complementarios sólo se confirmen con año, decisiones, huella y COG vigentes y auditables.

**Architecture:** El análisis seguirá produciendo filas temporales e incidencias; se ampliará el protocolo existente de decisiones para advertencias complementarias y la confirmación continuará siendo una única transacción con bloqueos. Ficha técnica conservará una relación al COG vigente y una fotografía histórica mediante una migración incremental.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, Inertia React 3, Wayfinder, Pest 4, TypeScript.

---

### Task 1: Huella obligatoria y advertencia de ejercicio

**Files:**
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFile.php`
- Modify: `app/Services/Finance/OwnRevenue/Imports/SupportingWorkbookParser.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php`

- [ ] **Step 1: Escribir pruebas fallidas para huella ausente, huella vencida y año distinto**

Agregar casos que construyan una revisión válida con `CaptureOwnRevenueImportAnalysisSnapshot`, que esperen rechazo cuando la huella sea nula o distinta, y que analicen cada formato con `detected_year = fiscal_year + 1` esperando una incidencia:

```php
expect($issue->code)->toBe('year.mismatch')
    ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Warning)
    ->and($issue->context)->toMatchArray([
        'detected_year' => $budget->fiscal_year + 1,
        'fiscal_year' => $budget->fiscal_year,
        'requires_decision' => true,
    ]);
```

- [ ] **Step 2: Ejecutar las pruebas y comprobar el fallo esperado**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

Expected: FAIL porque no se crea `year.mismatch` y una huella nula todavía se acepta.

- [ ] **Step 3: Pasar año detectado y fiscal al parser y exigir la huella**

La transacción inicial de análisis devolverá `detected_year`; el parser recibirá ambos años y agregará una incidencia de archivo cuando difieran:

```php
if ($detectedYear !== null && $detectedYear !== $fiscalYear) {
    $issues[] = new ImportIssueData(
        OwnRevenueImportIssueSeverity::Warning,
        'year.mismatch',
        'fiscal_year',
        'El archivo corresponde a un ejercicio distinto.',
        [
            'detected_year' => $detectedYear,
            'fiscal_year' => $fiscalYear,
            'requires_decision' => true,
        ],
    );
}
```

La confirmación usará la condición estricta:

```php
if ($file->analysis_fingerprint === null
    || ! hash_equals($file->analysis_fingerprint, $fingerprint)) {
    throw ValidationException::withMessages([
        'file' => 'Los datos de referencia cambiaron; vuelva a analizar el archivo.',
    ]);
}
```

- [ ] **Step 4: Ejecutar las pruebas focalizadas hasta obtener verde**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFile.php app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php app/Services/Finance/OwnRevenue/Imports/SupportingWorkbookParser.php tests/Feature/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php
git commit -m "fix: require current supporting import analysis"
```

### Task 2: Decisiones auditables en backend y vista previa

**Files:**
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecisionTest.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecision.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php`
- Modify: `app/Http/Controllers/Finance/OwnRevenueAbprePreviewController.php`
- Modify: `app/Services/Finance/OwnRevenue/Imports/OwnRevenueImportViewData.php`
- Modify: `resources/js/components/finance/own-revenue/imports/supporting-format-preview.tsx`
- Modify: `resources/js/types/finance-own-revenue-imports.ts`

- [ ] **Step 1: Escribir pruebas fallidas para decisiones complementarias**

Cubrir aceptación y rechazo de `year.mismatch` y `region.normalized`, revisión obsoleta, incidencia de otro archivo, usuario/fecha/justificación persistidos y bloqueo de confirmación sin una aceptación vigente:

```php
$decision = $issue->decisions()->sole();
expect($decision->resolution)->toBe('accepted')
    ->and($decision->resolved_by)->toBe($manager->id)
    ->and($decision->justification)->toBe('El archivo autorizado corresponde a esta planeación.')
    ->and($decision->resolved_value['analysis_revision'])->toBe($file->analysis_revision);
```

- [ ] **Step 2: Ejecutar las pruebas y comprobar que fallen por la restricción a Hoja de trabajo**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecisionTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

Expected: FAIL con “Esta incidencia no admite una decisión explícita” o confirmación permitida sin decisión.

- [ ] **Step 3: Generalizar el protocolo de decisiones y la validación de confirmación**

Permitir `work_sheet.abpre_mismatch`, `year.mismatch` y `region.normalized` únicamente cuando `requires_decision` sea verdadero. En confirmación, cargar incidencias y decisiones bajo bloqueo y exigir que la última decisión sea `accepted`, contenga `accepted: true` y la misma `analysis_revision`.

- [ ] **Step 4: Exponer advertencias y controles en la vista previa**

El controlador agregará `decision_warnings`; el componente mostrará cada advertencia con justificación y acciones Aceptar/Rechazar mediante `OwnRevenueImportDecisionController`. El botón de confirmación sólo aparecerá cuando todas tengan aceptación vigente.

```tsx
decisionForm.submit(
    OwnRevenueImportDecisionController({
        budget: budget.id,
        importFile: file.id,
        issue: warning.id,
    }),
    { preserveScroll: true },
);
```

- [ ] **Step 5: Ejecutar pruebas PHP, tipos y lint focalizados**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecisionTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php`

Run: `npm run types:check && npx eslint resources/js/components/finance/own-revenue/imports/supporting-format-preview.tsx`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecision.php app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php app/Http/Controllers/Finance/OwnRevenueAbprePreviewController.php app/Services/Finance/OwnRevenue/Imports/OwnRevenueImportViewData.php resources/js/components/finance/own-revenue/imports/supporting-format-preview.tsx resources/js/types/finance-own-revenue-imports.ts tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueImportDecisionTest.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
git commit -m "fix: audit supporting import warnings"
```

### Task 3: Referencia histórica del COG

**Files:**
- Create: `database/migrations/2026_07_14_173100_add_historical_cog_to_own_revenue_technical_sheet_needs_table.php`
- Modify: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueTechnicalSheetNeed.php`
- Modify: `database/factories/Finance/OwnRevenue/Imports/OwnRevenueTechnicalSheetNeedFactory.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

- [ ] **Step 1: Escribir pruebas fallidas para relación y fotografía del COG**

La prueba creará una partida del ejercicio, confirmará una ficha y verificará:

```php
expect($need->expense_classification_id)->toBe($classification->id)
    ->and($need->specific_item_code)->toBe($classification->specific_item_code)
    ->and($need->specific_item_name)->toBe($classification->specific_item_name)
    ->and($need->chapter_code)->toBe($classification->chapter_code)
    ->and($need->chapter_name)->toBe($classification->chapter_name);
```

Agregar otro caso que elimine o sustituya la partida después del análisis y espere rechazo sin crear necesidades.

- [ ] **Step 2: Ejecutar la prueba y comprobar el fallo por columnas inexistentes**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

Expected: FAIL por esquema o atributos históricos ausentes.

- [ ] **Step 3: Generar y completar la migración incremental**

Run: `php artisan make:migration add_historical_cog_to_own_revenue_technical_sheet_needs_table --table=own_revenue_technical_sheet_needs --no-interaction`

La migración agregará:

```php
$table->foreignId('expense_classification_id')->after('source_row_id')->constrained()->restrictOnDelete();
$table->string('specific_item_name')->after('specific_item_code');
$table->string('chapter_code')->after('specific_item_name');
$table->string('chapter_name')->after('chapter_code');
```

El `down()` eliminará primero la llave foránea y luego las cuatro columnas.

- [ ] **Step 4: Resolver el COG bajo transacción y persistir la fotografía**

Precargar las partidas del ejercicio por código, rechazar cualquier código inexistente y guardar FK, nombre específico, código de capítulo y nombre de capítulo. Agregar `expenseClassification(): BelongsTo` y actualizar `#[Fillable]` y factory.

- [ ] **Step 5: Ejecutar migración y pruebas focalizadas**

Run: `php artisan migrate --no-interaction`

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php`

Expected: PASS.

- [ ] **Step 6: Formatear PHP y commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php app/Models/Finance/OwnRevenue/Imports/OwnRevenueTechnicalSheetNeed.php database/factories/Finance/OwnRevenue/Imports/OwnRevenueTechnicalSheetNeedFactory.php database/migrations tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportSchemaTest.php
git commit -m "fix: preserve technical sheet classification history"
```

### Task 4: Verificación integral y auditoría final

**Files:**
- Modify only if a regression is discovered in files already listed above.

- [ ] **Step 1: Ejecutar la suite focalizada de importaciones**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports`

Expected: PASS.

- [ ] **Step 2: Ejecutar verificación integral una sola vez**

Run: `php artisan test --compact`

Run: `npm run test:frontend && npm run types:check && npx eslint . --ignore-pattern .worktrees && npm run build`

Expected: todas las pruebas y compilaciones pasan sin errores.

- [ ] **Step 3: Revisar el rango completo contra la especificación**

Run: `git diff 3d4155c..HEAD --check`

Comprobar que cada advertencia exigible deja decisión, toda confirmación exige huella, Ficha técnica congela el COG y los tres formatos detectan año distinto.

- [ ] **Step 4: Commit de cualquier ajuste exclusivamente derivado de la verificación**

Si la verificación obliga a corregir una regresión, agregar únicamente los archivos ya enumerados en las tareas anteriores y crear `test: cover supporting import safeguards`. Si no hay cambios, no crear un commit vacío.
