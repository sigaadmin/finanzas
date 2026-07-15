# Conciliación de actividades de archivos complementarios — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que Ficha técnica, Combustible y Viáticos se concilien por grupos con las actividades de la Hoja de trabajo, con reglas reutilizables, excepciones individuales y auditoría completa.

**Architecture:** Dos tablas append-only conservarán las revisiones de reglas y el historial polimórfico de asignaciones, mientras `own_revenue_activity_id` seguirá siendo la lectura vigente en cada registro complementario. Servicios separados normalizarán grupos y construirán la vista de conciliación; acciones transaccionales aplicarán reglas o excepciones y la confirmación futura reutilizará reglas activas.

**Tech Stack:** Laravel 13, PHP 8.5, Eloquent, Inertia Laravel 3, Inertia React 3, React 19, Wayfinder, Tailwind CSS 4 y Pest 4.

---

### Task 1: Persistir reglas revisables y asignaciones auditadas

**Files:**
- Create: `app/Enums/Finance/OwnRevenue/Imports/OwnRevenueActivityAssignmentMode.php`
- Create: `app/Enums/Finance/OwnRevenue/Imports/OwnRevenueActivityJustification.php`
- Create: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueActivityRule.php`
- Create: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueActivityAssignment.php`
- Create: `database/factories/Finance/OwnRevenue/Imports/OwnRevenueActivityRuleFactory.php`
- Create: `database/factories/Finance/OwnRevenue/Imports/OwnRevenueActivityAssignmentFactory.php`
- Create: `database/migrations/2026_07_14_235900_create_own_revenue_activity_rules_table.php`
- Create: `database/migrations/2026_07_14_235901_create_own_revenue_activity_assignments_table.php`
- Modify: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueTechnicalSheetNeed.php`
- Modify: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueFuelPlan.php`
- Modify: `app/Models/Finance/OwnRevenue/Imports/OwnRevenueTravelCommission.php`
- Modify: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationSchemaTest.php`

- [ ] **Step 1: Generar archivos Laravel**

Run:

```bash
php artisan make:enum Finance/OwnRevenue/Imports/OwnRevenueActivityAssignmentMode --string --no-interaction
php artisan make:enum Finance/OwnRevenue/Imports/OwnRevenueActivityJustification --string --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueActivityRule --factory --no-interaction
php artisan make:model Finance/OwnRevenue/Imports/OwnRevenueActivityAssignment --factory --no-interaction
php artisan make:migration create_own_revenue_activity_rules_table --no-interaction
php artisan make:migration create_own_revenue_activity_assignments_table --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationSchemaTest --no-interaction
```

Rename the generated migrations to the exact paths listed above so their order is deterministic.

- [ ] **Step 2: Write the failing schema test**

Test the current snapshot plus append-only history:

```php
test('activity reconciliation schema preserves active rules and assignment history', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->recycle($budget)->create();
    $file = OwnRevenueImportFile::factory()->recycle($budget)->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'status' => OwnRevenueImportFileStatus::Confirmed,
    ]);
    $need = OwnRevenueTechnicalSheetNeed::factory()->recycle([$budget, $file])->create();
    $rule = OwnRevenueActivityRule::factory()->recycle([$budget, $activity])->create([
        'format' => OwnRevenueImportFormat::TechnicalSheet,
        'group_key' => '21101|BOLIGRAFO AZUL',
        'group_hash' => hash('sha256', 'technical_sheet|21101|BOLIGRAFO AZUL'),
    ]);

    $assignment = OwnRevenueActivityAssignment::factory()
        ->recycle([$budget, $file, $activity, $rule])
        ->for($need, 'assignable')
        ->create();

    expect($assignment->assignable->is($need))->toBeTrue()
        ->and($need->activityAssignments()->count())->toBe(1)
        ->and($budget->activityRules()->count())->toBe(1);
});
```

- [ ] **Step 3: Run the schema test and verify RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationSchemaTest.php`

Expected: FAIL because the models and tables do not exist.

- [ ] **Step 4: Implement enums, tables, models and relationships**

Use backed enums:

```php
enum OwnRevenueActivityAssignmentMode: string
{
    case GroupRule = 'group_rule';
    case AutomaticRule = 'automatic_rule';
    case IndividualException = 'individual_exception';
}

enum OwnRevenueActivityJustification: string
{
    case WorkSheetMatch = 'work_sheet_match';
    case DescriptionClassification = 'description_classification';
    case AdministrativeCriterion = 'administrative_criterion';
    case Other = 'other';
}
```

The rules migration must include budget, format, group key/hash, readable payload JSON, activity and snapshots, justification, creator, active/deactivation fields and `replaces_rule_id`. Add an index on `(own_revenue_budget_id, format, group_hash, is_active)`.

The assignments migration must include budget, import file, nullable rule, `assignable_type/id`, nullable previous activity, new activity and snapshots, mode, group key/hash, justification, user and `assigned_at`. Add indexes for `(assignable_type, assignable_id, assigned_at)` and `(own_revenue_budget_id, own_revenue_import_file_id)`.

Models must use `#[Fillable]`, enum/JSON/datetime casts, typed `BelongsTo`, `MorphTo`, `HasMany` and `MorphMany` relations. Add this relation to each supporting record:

```php
/** @return MorphMany<OwnRevenueActivityAssignment, $this> */
public function activityAssignments(): MorphMany
{
    return $this->morphMany(OwnRevenueActivityAssignment::class, 'assignable');
}
```

- [ ] **Step 5: Verify GREEN and format PHP**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationSchemaTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/Finance/OwnRevenue/Imports app/Models/Finance/OwnRevenue database/factories/Finance/OwnRevenue/Imports database/migrations tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationSchemaTest.php
git commit -m "feat: store activity reconciliation history"
```

### Task 2: Normalizar grupos y construir candidatos y diferencias

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Imports/OwnRevenueActivityGroupKey.php`
- Create: `app/Services/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewData.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewDataTest.php`

- [ ] **Step 1: Create the service test with Artisan**

Run:

```bash
php artisan make:class Services/Finance/OwnRevenue/Imports/OwnRevenueActivityGroupKey --no-interaction
php artisan make:class Services/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewData --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewDataTest --no-interaction
```

- [ ] **Step 2: Write failing grouping and candidate tests**

Create fixtures with two activities sharing `26101` and `37501`, repeated reasons with case/accent/space variations, and technical needs sharing item/description. Assert:

```php
$data = app(OwnRevenueActivityReconciliationViewData::class)->forBudget($budget);

expect($data['summary'])->toMatchArray([
    'total' => 5,
    'assigned' => 0,
    'pending' => 5,
    'complete' => false,
])->and($data['formats']['fuel']['groups'])->toHaveCount(1)
  ->and($data['formats']['fuel']['groups'][0]['record_count'])->toBe(2)
  ->and($data['formats']['fuel']['groups'][0]['candidate_activity_codes'])->toBe(['A02', 'A04']);
```

Also assert exact monetary mappings:

```php
expect($data['formats']['fuel']['detail_cents'])->toBe('150000')
    ->and($data['formats']['fuel']['work_sheet_cents'])->toBe('200000')
    ->and($data['formats']['fuel']['difference_cents'])->toBe('-50000');
```

- [ ] **Step 3: Run and verify RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewDataTest.php`

Expected: FAIL because both services are missing.

- [ ] **Step 4: Implement deterministic group keys**

`OwnRevenueActivityGroupKey` must expose:

```php
public function forTechnicalSheetNeed(OwnRevenueTechnicalSheetNeed $need): string;
public function forFuelPlan(OwnRevenueFuelPlan $plan): string;
public function forTravelCommission(OwnRevenueTravelCommission $commission): string;
public function hash(OwnRevenueImportFormat $format, string $groupKey): string;
```

Normalize with `Str::of($value)->ascii()->squish()->upper()`. Technical keys are `specific_item_code|normalized_description`; fuel and travel keys use normalized reason only.

- [ ] **Step 5: Implement read-only reconciliation data**

`forBudget()` must:

1. select the latest confirmed Work Sheet and confirmed supporting file per format;
2. eager-load activities, Work Sheet months and supporting records;
3. group only records from current confirmed files;
4. resolve candidates using item codes `26101`, `37501`, `37101` and technical item/month evidence;
5. return string cent amounts through `PortableIntegerAmount`;
6. expose group hashes, readable labels, candidate activities, active rule, assignment state and counters;
7. return empty-state reasons without mutating data.

- [ ] **Step 6: Verify GREEN**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewDataTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Imports tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationViewDataTest.php
git commit -m "feat: group supporting imports for reconciliation"
```

### Task 3: Crear y aplicar reglas de grupo transaccionales

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRule.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleRequest.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueActivityRuleController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleTest.php`

- [ ] **Step 1: Generate the request, controller and test**

Run:

```bash
php artisan make:class Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRule --no-interaction
php artisan make:request Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleRequest --no-interaction
php artisan make:controller Finance/OwnRevenueActivityRuleController --invokable --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleTest --no-interaction
```

- [ ] **Step 2: Write failing domain and HTTP tests**

Post this payload as FinanceManager:

```php
[
    'format' => 'fuel',
    'group_hash' => $groupHash,
    'activity_id' => $activity->id,
    'justification' => 'description_classification',
    'justification_note' => null,
    'expected_work_sheet_file_id' => $workSheet->id,
    'expected_supporting_file_id' => $fuelFile->id,
]
```

Assert one active rule, every matching current record updated, one assignment per changed record, historical records untouched, and a second selection deactivates the old rule and creates a revision. Add tests for `Other` without note, foreign activities, stale file IDs, consultation roles and unauthenticated access.

- [ ] **Step 3: Run and verify RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleTest.php`

Expected: FAIL because route/action do not exist.

- [ ] **Step 4: Implement validation and route**

Request rules:

```php
return [
    'format' => ['required', Rule::enum(OwnRevenueImportFormat::class)->only([
        OwnRevenueImportFormat::TechnicalSheet,
        OwnRevenueImportFormat::Fuel,
        OwnRevenueImportFormat::TravelExpenses,
    ])],
    'group_hash' => ['required', 'string', 'size:64'],
    'activity_id' => ['required', 'integer'],
    'justification' => ['required', Rule::enum(OwnRevenueActivityJustification::class)],
    'justification_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(
        $this->string('justification')->toString() === OwnRevenueActivityJustification::Other->value,
    )],
    'expected_work_sheet_file_id' => ['required', 'integer'],
    'expected_supporting_file_id' => ['required', 'integer'],
];
```

Add POST route `own-revenue/budgets/{budget}/imports/reconciliation/rules` named `finance.own-revenue.budgets.imports.reconciliation.rules.store`.

- [ ] **Step 5: Implement the transactional action**

`handle()` must authorize `confirmImports`, lock the budget and expected files, verify they are current confirmed versions, resolve the server-side group by hash, validate the selected activity belongs to the budget, deactivate the previous rule, create the new rule, update matching current records and append assignments. Use `DB::transaction(..., attempts: 3)` and the approved stale message.

- [ ] **Step 6: Verify GREEN**

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRule.php app/Http/Requests/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleRequest.php app/Http/Controllers/Finance/OwnRevenueActivityRuleController.php routes/web.php tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityRuleTest.php
git commit -m "feat: apply auditable activity group rules"
```

### Task 4: Registrar excepciones individuales

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityException.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionRequest.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueActivityExceptionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionTest.php`

- [ ] **Step 1: Generate files**

```bash
php artisan make:class Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityException --no-interaction
php artisan make:request Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionRequest --no-interaction
php artisan make:controller Finance/OwnRevenueActivityExceptionController --invokable --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionTest --no-interaction
```

- [ ] **Step 2: Write failing tests**

Assert that an exception changes exactly one current record, creates `IndividualException` history, leaves the active group rule unchanged, records previous/new activity and rejects a record from a replaced file, another format/table or another budget.

- [ ] **Step 3: Run and verify RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionTest.php`

Expected: FAIL because endpoint and action are missing.

- [ ] **Step 4: Implement request, route and action**

Use POST route `own-revenue/budgets/{budget}/imports/reconciliation/records/{record}/activity` named `finance.own-revenue.budgets.imports.reconciliation.records.activity.store`. Validate the same snapshot fields plus `format`, `activity_id`, justification and note. Resolve the model class from the validated supporting format, never from client-provided class names.

Inside a transaction, lock the budget, expected files and scoped record; update only `own_revenue_activity_id`; append an assignment with `rule_id = null` and `mode = IndividualException`; never revise the group rule.

- [ ] **Step 5: Verify GREEN and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionTest.php
git add app/Actions/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityException.php app/Http/Requests/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionRequest.php app/Http/Controllers/Finance/OwnRevenueActivityExceptionController.php routes/web.php tests/Feature/Finance/OwnRevenue/Imports/StoreOwnRevenueActivityExceptionTest.php
git commit -m "feat: record individual activity exceptions"
```

### Task 5: Reutilizar reglas al confirmar versiones futuras

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Imports/ApplyOwnRevenueActivityRule.php`
- Modify: `app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php`
- Modify: `app/Http/Controllers/Finance/OwnRevenueSupportingConfirmationController.php`
- Test: `tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

- [ ] **Step 1: Add failing confirmation tests**

Generate the action first with `php artisan make:class Actions/Finance/OwnRevenue/Imports/ApplyOwnRevenueActivityRule --no-interaction`.

Create active rules before confirming a new supporting version. Assert matching records receive the activity and `AutomaticRule` assignment with the confirming user; unmatched groups remain null. Add tests proving inactive/foreign rules are ignored and a rule pointing to an invalid activity rolls back all new records and confirmation status.

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php`

Expected: FAIL because confirmation leaves every activity null.

- [ ] **Step 3: Implement reusable application action**

`ApplyOwnRevenueActivityRule::handle(Model $record, OwnRevenueImportFormat $format, OwnRevenueImportFile $file, User $user)` must compute the group key, find the active rule scoped to budget/format/hash, validate its activity, update the record and append an automatic assignment. Return the unchanged record when no rule exists.

Inject it into `ConfirmOwnRevenueSupportingImport`, call it immediately after each supporting record is created, and update the success flash to `Archivo confirmado correctamente. Las reglas de actividad disponibles fueron aplicadas.`

- [ ] **Step 4: Verify GREEN and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php
git add app/Actions/Finance/OwnRevenue/Imports/ApplyOwnRevenueActivityRule.php app/Actions/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImport.php app/Http/Controllers/Finance/OwnRevenueSupportingConfirmationController.php tests/Feature/Finance/OwnRevenue/Imports/ConfirmOwnRevenueSupportingImportTest.php
git commit -m "feat: reuse activity rules on future imports"
```

### Task 6: Exponer la conciliación mediante Inertia y modal de detalle

**Files:**
- Create: `app/Http/Controllers/Finance/OwnRevenueActivityReconciliationController.php`
- Create: `resources/js/pages/finance/own-revenue/imports/reconciliation.tsx`
- Create: `resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js`
- Create: `resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.d.ts`
- Modify: `resources/js/pages/finance/own-revenue/imports/show.tsx`
- Modify: `resources/js/types/finance-own-revenue-imports.ts`
- Modify: `routes/web.php`
- Create: `tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationNavigationTest.php`
- Create: `tests/Frontend/activity-reconciliation-state.test.mjs`

- [ ] **Step 1: Generate controller and tests**

```bash
php artisan make:controller Finance/OwnRevenueActivityReconciliationController --invokable --no-interaction
php artisan make:test --pest Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationNavigationTest --no-interaction
```

- [ ] **Step 2: Write failing backend navigation tests**

Assert the GET route returns component `finance/own-revenue/imports/reconciliation` with budget, activities, permissions, snapshots, summaries, format groups and selected group detail. Verify view roles receive data but false mutation permissions, unauthorized roles receive 403, and stale/unknown group hashes do not expose records.

- [ ] **Step 3: Write failing frontend state tests**

Specify pure URL/query and presentation behavior:

```js
assert.deepEqual(openActivityGroup('/imports/reconciliation?format=fuel', 'abc'), {
    format: 'fuel',
    group: 'abc',
});
assert.equal(reconciliationStatusLabel({ total: 4, pending: 0 }), 'Actividades conciliadas');
assert.equal(reconciliationStatusLabel({ total: 4, pending: 1 }), '1 registro pendiente');
```

- [ ] **Step 4: Run and verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationNavigationTest.php
npm run test:frontend
```

Expected: FAIL because route, page and helpers are missing.

- [ ] **Step 5: Implement controller, route and TypeScript contracts**

Add GET route `own-revenue/budgets/{budget}/imports/reconciliation` named `finance.own-revenue.budgets.imports.reconciliation.show`. Authorize `viewImports`; delegate all props to `OwnRevenueActivityReconciliationViewData`; expose `manage = manageImports && confirmImports`.

Define explicit types for summaries, candidates, groups, group records, snapshot IDs, rules and assignment history. Regenerate Wayfinder with `php artisan wayfinder:generate --no-interaction`.

- [ ] **Step 6: Implement the page and modal**

The page must provide:

- internal back link to imports;
- overall and per-format status cards;
- tabs for the three formats;
- paginated group list;
- readable amounts and differences;
- activity selector, justification selector, conditional note and group submit form;
- `Ver detalle` using the existing Dialog components and query-preserved Inertia navigation;
- individual exception form inside the modal;
- read-only rendering for consultation roles;
- no `target="_blank"`, internal keys or variable names.

Add `Conciliar actividades` to the imports workspace only when controller props indicate a confirmed Work Sheet and at least one confirmed supporting format.

- [ ] **Step 7: Verify GREEN**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationNavigationTest.php
npm run test:frontend
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/imports/reconciliation.tsx resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js resources/js/pages/finance/own-revenue/imports/show.tsx
npx prettier --check resources/js/pages/finance/own-revenue/imports/reconciliation.tsx resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.d.ts resources/js/types/finance-own-revenue-imports.ts tests/Frontend/activity-reconciliation-state.test.mjs
```

Expected: all commands PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Finance/OwnRevenueActivityReconciliationController.php resources/js/pages/finance/own-revenue/imports/reconciliation.tsx resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.d.ts resources/js/pages/finance/own-revenue/imports/show.tsx resources/js/types/finance-own-revenue-imports.ts routes/web.php tests/Feature/Finance/OwnRevenue/Imports/OwnRevenueActivityReconciliationNavigationTest.php tests/Frontend/activity-reconciliation-state.test.mjs
git commit -m "feat: review supporting activity reconciliation"
```

### Task 7: Verificación integral y archivos reales

**Files:**
- Modify only if a failing assertion reveals a reconciliation defect.

- [ ] **Step 1: Run formatting and complete automated verification once**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test:frontend
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/imports/reconciliation.tsx resources/js/components/finance/own-revenue/imports/activity-reconciliation-state.js resources/js/pages/finance/own-revenue/imports/show.tsx
npm run build
git diff --check
```

Expected: 0 failures. Use scoped ESLint because the repository contains an unrelated historical worktree with compiled artifacts.

- [ ] **Step 2: Verify the local real-data read model before mutations**

Open budget 1 reconciliation and confirm the authoritative baseline:

- 122 Ficha técnica needs;
- 156 Combustible plans;
- 67 Viáticos commissions;
- candidates include multiple activities for `26101` and `37501`;
- differences are visible and non-blocking;
- no assignment occurs merely by opening the page.

- [ ] **Step 3: Exercise one reversible test group in browser only with test fixtures**

Use an automated feature/browser fixture, not the user's confirmed production-like records, to verify group assignment, modal exception, page refresh, console errors and same-tab navigation. Do not mutate budget 1 merely for verification.

- [ ] **Step 4: Review final status and commit any verification-only fixes**

```bash
git status -sb
git log --oneline --decorate -8
```

Expected: clean feature branch with all prior task commits and no generated Wayfinder/build artifacts staged.
