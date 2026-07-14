# Reanálisis desde la vista previa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir reanalizar cualquiera de los cinco formatos desde su vista previa y permanecer en esa pantalla con la información actualizada.

**Architecture:** Se reutiliza el endpoint y la acción de análisis existentes. Un Form Request acepta únicamente el booleano `return_to_preview`; el controlador selecciona una de dos rutas nombradas después del análisis. La cabecera común de la vista previa consume la matriz compartida de estados para mostrar una sola acción Inertia para los cinco formatos.

**Tech Stack:** Laravel 13, Inertia.js 3, React 19, Wayfinder, Pest 4, Node Test Runner.

---

### Task 1: Retorno seguro a la vista previa

**Files:**
- Create: `app/Http/Requests/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileRequest.php`
- Modify: `app/Http/Controllers/Finance/OwnRevenueImportAnalysisController.php`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php`

- [ ] **Step 1: Escribir la prueba HTTP que falla**

Extender `analysis endpoint flashes a consumable toast for failed and successful analyses` con un argumento `bool $returnToPreview`, enviar:

```php
[
    'return_to_preview' => $returnToPreview,
]
```

y calcular el destino esperado:

```php
$expectedRoute = $returnToPreview
    ? route('finance.own-revenue.budgets.imports.files.preview', [$budget, $file])
    : route('finance.own-revenue.budgets.imports.show', $budget);
```

Agregar al dataset al menos un caso `ready` con `return_to_preview => true` y conservar uno con `false`. Añadir una prueba separada que envíe `return_to_preview => 'not-a-boolean'` y espere `assertSessionHasErrors('return_to_preview')` sin invocar el analizador.

- [ ] **Step 2: Ejecutar la prueba para comprobar que falla**

Run:

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php --filter='analysis endpoint'
```

Expected: FAIL porque el controlador todavía redirige siempre al listado y no valida `return_to_preview`.

- [ ] **Step 3: Crear el Form Request**

Run:

```bash
php artisan make:request Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileRequest --no-interaction
```

Implementar:

```php
public function authorize(): bool
{
    return true;
}

/** @return array<string, array<mixed>> */
public function rules(): array
{
    return [
        'return_to_preview' => ['sometimes', 'boolean'],
    ];
}
```

La autorización de negocio permanece en `AnalyzeOwnRevenueImportFile`, que ya ejecuta `Gate::forUser(...)->authorize('manageImports', ...)` dentro del agregado.

- [ ] **Step 4: Redirigir mediante rutas nombradas**

Inyectar `AnalyzeOwnRevenueImportFileRequest $request` en el controlador, usar `$request->user()` y terminar con:

```php
if ($request->boolean('return_to_preview')) {
    return to_route('finance.own-revenue.budgets.imports.files.preview', [
        'budget' => $budget,
        'importFile' => $importFile,
    ]);
}

return to_route('finance.own-revenue.budgets.imports.show', $budget);
```

No aceptar una URL de retorno del cliente.

- [ ] **Step 5: Ejecutar la prueba dirigida y formatear PHP**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php --filter='analysis endpoint'
```

Expected: PASS.

- [ ] **Step 6: Crear el commit del backend**

```bash
git add app/Http/Requests/Finance/OwnRevenue/Imports/AnalyzeOwnRevenueImportFileRequest.php app/Http/Controllers/Finance/OwnRevenueImportAnalysisController.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportManagementTest.php
git commit -m "fix: return import reanalysis to preview"
```

### Task 2: Acción común para los cinco formatos

**Files:**
- Modify: `resources/js/components/finance/own-revenue/imports/import-workspace-state.js`
- Modify: `resources/js/pages/finance/own-revenue/imports/preview.tsx`
- Modify: `tests/Frontend/import-workspace-state.test.mjs`
- Modify: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php`

- [ ] **Step 1: Escribir las pruebas de elegibilidad que fallan**

En `import-workspace-state.test.mjs`, cubrir los cinco formatos:

```js
for (const format of [
    'abpre',
    'work_sheet',
    'technical_sheet',
    'fuel',
    'travel_expenses',
]) {
    assert.equal(
        importFilePresentation({
            status: 'ready',
            format,
            analyzed: true,
            issueCount: 0,
            canReclassify: false,
        }).canAnalyze,
        true,
    );
}
```

Cubrir también que `analyzing`, `confirmed`, `replaced` y `discarded` producen `canAnalyze === false` para cada formato.

En `OwnRevenueImportNavigationTest.php`, ampliar la prueba estática de navegación para exigir que `preview.tsx` contenga `OwnRevenueImportAnalysisController`, `return_to_preview` y `Volver a analizar`, y que no contenga `target="_blank"`.

- [ ] **Step 2: Ejecutar las pruebas para comprobar que fallan**

Run:

```bash
npm run test:frontend
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
```

Expected: FAIL porque `ready` no permite análisis y la vista previa carece de la acción.

- [ ] **Step 3: Habilitar estados mutables en la matriz compartida**

En `importFilePresentation`, reemplazar las listas distintas por una lista común:

```js
const analyzableStatuses = [
    'uploaded',
    'parser_pending',
    'needs_correction',
    'ready',
    'failed',
];

const canAnalyze =
    (isAbpre || isSupportingFormat) && analyzableStatuses.includes(status);
```

Devolver `canAnalyze` sin habilitar estados terminales ni `analyzing`.

- [ ] **Step 4: Añadir la acción a la cabecera común**

En `preview.tsx`:

```tsx
const analysisForm = useForm({ return_to_preview: true });
const canAnalyze = permissions.manage && status.canAnalyze;

const analyzeFile = (): void => {
    if (!canAnalyze) {
        return;
    }

    analysisForm.submit(
        OwnRevenueImportAnalysisController({
            budget: budget.id,
            importFile: selectedFile.id,
        }),
        { preserveScroll: true },
    );
};
```

Importar `useForm`, `LoaderCircle`, `Search`, el controlador Wayfinder e `InputError`. En la zona de acciones de la cabecera renderizar:

```tsx
{canAnalyze && (
    <Button
        type="button"
        variant="outline"
        onClick={analyzeFile}
        disabled={analysisForm.processing}
    >
        {analysisForm.processing ? (
            <LoaderCircle className="size-4 animate-spin" />
        ) : (
            <Search className="size-4" />
        )}
        {analysisForm.processing
            ? 'Analizando…'
            : selectedFile.analyzed
              ? 'Volver a analizar'
              : 'Analizar archivo'}
    </Button>
)}
```

Mostrar bajo la cabecera:

```tsx
<InputError message={analysisForm.errors.file ?? analysisForm.errors.return_to_preview} />
```

La redirección del servidor hará que Inertia recargue la misma vista con props nuevas.

- [ ] **Step 5: Formatear y ejecutar pruebas dirigidas**

Run:

```bash
npx prettier --write resources/js/pages/finance/own-revenue/imports/preview.tsx resources/js/components/finance/own-revenue/imports/import-workspace-state.js tests/Frontend/import-workspace-state.test.mjs
npm run test:frontend
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/imports/preview.tsx resources/js/components/finance/own-revenue/imports/import-workspace-state.js
```

Expected: todos los comandos terminan correctamente.

- [ ] **Step 6: Crear el commit de interfaz**

```bash
git add resources/js/components/finance/own-revenue/imports/import-workspace-state.js resources/js/pages/finance/own-revenue/imports/preview.tsx tests/Frontend/import-workspace-state.test.mjs tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueImportNavigationTest.php
git commit -m "fix: reanalyze imports from preview"
```

### Task 3: Verificación integral

**Files:**
- Verify only; no planned production files.

- [ ] **Step 1: Ejecutar la verificación completa**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test:frontend
npm run types:check
npm run lint:check
npx prettier --check resources/js/pages/finance/own-revenue/imports/preview.tsx resources/js/components/finance/own-revenue/imports/import-workspace-state.js tests/Frontend/import-workspace-state.test.mjs
npm run build
git diff --check
```

Expected: suite PHP sin fallos, 21 o más pruebas de interfaz aprobadas, tipos y lint sin errores, archivos modificados formateados, compilación exitosa y diff sin errores de espacios.

- [ ] **Step 2: Revisar el estado final**

Run:

```bash
git status --short
git log --oneline -3
```

Expected: sólo aparecen cambios intencionales ya comprometidos y los commits del plan están sobre `codex/reanalysis-preview`.
