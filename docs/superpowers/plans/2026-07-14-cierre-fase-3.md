# Cierre de Fase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Validar y autorizar un presupuesto inicial inmutable desde una propuesta ajustada, y generar los cinco formatos oficiales XLSX con auditoría y almacenamiento privado.

**Architecture:** Una revisión final de sólo lectura calcula bloqueos y huellas. La autorización crea en una transacción un snapshot independiente del presupuesto inicial y cambia el estado anual. Los exportadores consumen únicamente ese snapshot mediante un contrato común y registran cada archivo generado.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia 3, React 19, Wayfinder, Tailwind CSS 4, Pest 4 y `phpoffice/phpspreadsheet` autorizado.

---

## Precondición

Ejecutar este plan sólo después de integrar y verificar `2026-07-14-planeacion-editable.md`. Debe existir una propuesta `Adjusted` conciliada y su proyector canónico.

### Task 1: Persistir el presupuesto inicial y las exportaciones

**Files:**
- Create models/factories/migrations for `OwnRevenueInitialBudget`, `OwnRevenueInitialBudgetLine`, `OwnRevenueInitialBudgetMonth`, `OwnRevenueInitialTechnicalNeed`, `OwnRevenueInitialFuelNeed`, `OwnRevenueInitialTravelCommission`, `OwnRevenueInitialTravelParticipant`, `OwnRevenueExport`
- Modify: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialBudgetSchemaTest.php`

- [ ] **Step 1: Write the failing schema test**

Create an initial budget linked to an Adjusted proposal, summarized lines/months, one specialized record of each format, a travel participant and one export. Assert immutable source IDs, 64-character fingerprint, actor/time, exact totals and private storage metadata.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialBudgetSchemaTest.php
```

- [ ] **Step 3: Implement schema and models**

`own_revenue_initial_budgets` contains budget, adjusted proposal, five source file IDs, parameter/COG snapshot JSON, total cents, fingerprint, authorized_by/at and timestamps. Lines contain activity/COG/institution/region snapshots and annual cents; months contain month and cents. Specialized snapshot tables preserve every authorized technical need, fuel need, travel commission and participant, including calculation inputs, applied rates, output amounts, stable keys and ordering. Unique one initial budget per annual budget.

`own_revenue_exports` contains initial budget, format enum, template version, disk/path/name/MIME/size/hash, total cents, generated_by/at. Unique storage path and index by initial budget/format/date.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialBudgetSchemaTest.php
git add app/Models/Finance/OwnRevenue database/migrations database/factories/Finance/OwnRevenue app/Models/Finance/OwnRevenue/OwnRevenueBudget.php tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialBudgetSchemaTest.php
git commit -m "feat: store immutable initial budgets"
```

### Task 2: Construir la revisión final de autorización

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationReadiness.php`
- Create: `app/Services/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationViewData.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueInitialAuthorizationController.php`
- Create: `resources/js/pages/finance/own-revenue/authorization/show.tsx`
- Modify: `routes/web.php`
- Modify TypeScript contracts
- Test: `tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationNavigationTest.php`

- [ ] **Step 1: Write failing readiness/navigation tests**

Assert each blocker independently: no Adjusted proposal, stale source, missing format, pending activity, cut imbalance, invalid COG, non-`02-001`, annual parameter, institution/signatory, activity/part/month mismatch and blocking import issue. Assert a clean fixture returns `ready=true`, exact totals/fingerprint and manager permissions; assistants/auditors are read-only.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationNavigationTest.php
```

- [ ] **Step 3: Implement service and page**

Readiness returns named checks with `passed`, user-facing label, explanation and resolution URL. The page shows overall readiness, exact comparison totals, source versions, signatories and explicit warning that authorization is irreversible. It contains no mutation when `authorizeInitialBudget` is false.

- [ ] **Step 4: Verify and commit**

```bash
php artisan wayfinder:generate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationNavigationTest.php
npm run types:check
git add app/Services/Finance/OwnRevenue/Authorization app/Http/Controllers/Finance/OwnRevenueInitialAuthorizationController.php resources/js/pages/finance/own-revenue/authorization resources/js/types routes/web.php tests/Feature/Finance/OwnRevenue/Authorization/OwnRevenueInitialAuthorizationNavigationTest.php
git commit -m "feat: review initial budget readiness"
```

### Task 3: Autorizar el presupuesto inicial transaccionalmente

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Authorization/AuthorizeOwnRevenueInitialBudget.php`
- Create request/controller/route
- Modify policy
- Test: `tests/Feature/Finance/OwnRevenue/Authorization/AuthorizeOwnRevenueInitialBudgetTest.php`

- [ ] **Step 1: Write failing tests**

POST expected proposal ID, readiness fingerprint and five source IDs. Assert snapshot lines/months equal the Adjusted projection, budget becomes `InitialAuthorized`, actor/time/fingerprint persist and proposal/import evidence stays unchanged. Add stale race rollback, duplicate authorization, invalid role, guest and post-authorization edit rejection.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/AuthorizeOwnRevenueInitialBudgetTest.php
```

- [ ] **Step 3: Implement authorization**

```php
public function handle(
    OwnRevenueBudget $budget,
    User $user,
    int $expectedProposalId,
    array $expectedFileIds,
    string $expectedFingerprint,
): OwnRevenueInitialBudget;
```

Authorize Owner/Admin/FinanceManager only. Inside `DB::transaction(..., attempts: 3)`, lock budget/proposal/sources, recompute readiness, create snapshot lines/months, verify totals, store canonical fingerprint and update status. Refuse when an initial snapshot already exists.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Authorization/AuthorizeOwnRevenueInitialBudgetTest.php
git add app/Actions/Finance/OwnRevenue/Authorization app/Http/Requests/Finance/OwnRevenue/Authorization app/Http/Controllers/Finance app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php routes/web.php tests/Feature/Finance/OwnRevenue/Authorization/AuthorizeOwnRevenueInitialBudgetTest.php
git commit -m "feat: authorize immutable initial budgets"
```

### Task 4: Instalar y encapsular el escritor XLSX

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `app/Services/Finance/OwnRevenue/Exports/OwnRevenueWorkbookWriter.php`
- Create: `app/Services/Finance/OwnRevenue/Exports/PhpSpreadsheetWorkbookWriter.php`
- Create: `app/Services/Finance/OwnRevenue/Exports/OwnRevenueWorkbook.php`
- Modify service container binding
- Test: `tests/Unit/Finance/OwnRevenue/Exports/OwnRevenueWorkbookWriterTest.php`

- [ ] **Step 1: Verify package compatibility and install**

Use Laravel Boost/docs and official PhpSpreadsheet documentation, then:

```bash
composer require phpoffice/phpspreadsheet --no-interaction
```

Do not add another spreadsheet package.

- [ ] **Step 2: Write the failing contract test**

Define a small workbook with string codes preserving leading zeros, integer cents rendered as currency, UTF-8 text, one formula and print settings. Assert a written file can be reopened and values/formula/styles remain correct.

- [ ] **Step 3: Implement the adapter**

The domain-facing contract accepts sheet DTOs and returns bytes plus MIME/extension. Keep PhpSpreadsheet classes behind the adapter so projectors do not depend on library APIs.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/Finance/OwnRevenue/Exports/OwnRevenueWorkbookWriterTest.php
git add composer.json composer.lock app/Services/Finance/OwnRevenue/Exports app/Providers tests/Unit/Finance/OwnRevenue/Exports/OwnRevenueWorkbookWriterTest.php
git commit -m "build: add xlsx writer for own revenue exports"
```

### Task 5: Generar ABPRE y Hoja de trabajo

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Exports/AbpreWorkbookProjector.php`
- Create: `app/Services/Finance/OwnRevenue/Exports/WorkSheetWorkbookProjector.php`
- Test: `tests/Feature/Finance/OwnRevenue/Exports/AbpreAndWorkSheetExportTest.php`

- [ ] **Step 1: Write failing structural tests from official samples**

Assert sheet names, exact headers, institution/activity/region codes as strings, twelve months, annual formulas/totals, ordering and values from the initial snapshot. Reopen output with PhpSpreadsheet. If an official template is absent, stop this task and request it; do not invent headers.

- [ ] **Step 2: Implement pure projectors**

Projectors accept only `OwnRevenueInitialBudget` and return `OwnRevenueWorkbook`. They must not query current proposals or imports after loading the snapshot.

- [ ] **Step 3: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports/AbpreAndWorkSheetExportTest.php
git add app/Services/Finance/OwnRevenue/Exports tests/Feature/Finance/OwnRevenue/Exports/AbpreAndWorkSheetExportTest.php
git commit -m "feat: generate abpre and work sheet exports"
```

### Task 6: Generar Ficha técnica, Combustible y Viáticos

**Files:**
- Create three focused workbook projectors under `app/Services/Finance/OwnRevenue/Exports/`
- Test: `tests/Feature/Finance/OwnRevenue/Exports/SupportingWorkbookExportTest.php`

- [ ] **Step 1: Write failing dataset tests for the three official samples**

For each format assert exact sheet/header structure, region `02-001`, ordering, source-domain fields, formulas/totals, decimal display and round-trip readability. Technical totals must equal adjusted need totals; Fuel must expose operational month and April budget application correctly; Travel must preserve commission/participant and UMA/rate snapshots.

- [ ] **Step 2: Implement projectors**

Each projector is independent and maps only the authorized snapshot and its specialized snapshot relations. It must never query the mutable or historical proposal tables to construct workbook content.

- [ ] **Step 3: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports/SupportingWorkbookExportTest.php
git add app/Services/Finance/OwnRevenue/Exports tests/Feature/Finance/OwnRevenue/Exports/SupportingWorkbookExportTest.php
git commit -m "feat: generate supporting budget workbooks"
```

### Task 7: Almacenar, auditar y descargar exportaciones

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Exports/GenerateOwnRevenueExport.php`
- Create request/controller/routes
- Create: `resources/js/pages/finance/own-revenue/exports/index.tsx`
- Modify authorization dashboard and types
- Test: `tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueExportTest.php`

- [ ] **Step 1: Write failing tests**

Assert manager generates each format, bytes are private, DB metadata/hash/size/totals match, duplicate generation creates a new audited version, authorized roles download, unauthorized users receive 403, missing physical files fail safely, and a writer/storage exception leaves no DB row or orphan file.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueExportTest.php
```

- [ ] **Step 3: Implement action and UI**

Dispatch projector by validated enum, write to `own-revenue/{budget_id}/exports/{initial_budget_id}/`, hash bytes before persistence, save metadata transactionally and delete the physical file if DB persistence fails. The page lists format, generated date/user, size and download; generation controls require authorization.

- [ ] **Step 4: Verify and commit**

```bash
php artisan wayfinder:generate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueExportTest.php
npm run types:check
git add app/Actions/Finance/OwnRevenue/Exports app/Http/Requests/Finance/OwnRevenue/Exports app/Http/Controllers/Finance resources/js/pages/finance/own-revenue/exports resources/js/pages/finance/own-revenue/authorization resources/js/types routes/web.php tests/Feature/Finance/OwnRevenue/Exports/GenerateOwnRevenueExportTest.php
git commit -m "feat: audit private own revenue exports"
```

### Task 8: Verificar el cierre completo de Fase 3

**Files:**
- Modify only when a failing assertion reveals a defect.

- [ ] **Step 1: Run the complete verification once**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test:frontend
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/planning resources/js/pages/finance/own-revenue/authorization resources/js/pages/finance/own-revenue/exports resources/js/components/finance/own-revenue
npm run build
git diff --check
```

- [ ] **Step 2: Verify official workbook samples**

Using test fixtures, authorize a complete budget and generate all five files. Reopen them, compare sheet/header/formula/totals against official samples and verify no browser/backend errors. Do not authorize or export budget 1 merely for verification.

- [ ] **Step 3: Confirm global acceptance**

Verify: proposal adjusted, initial snapshot immutable, status `InitialAuthorized`, all five exports downloadable, imports unchanged, audit chain complete and post-authorization planning mutations rejected.

- [ ] **Step 4: Record final checkpoint**

```bash
git status -sb
git log --oneline --decorate -15
```

The feature branch must be clean before integration.
