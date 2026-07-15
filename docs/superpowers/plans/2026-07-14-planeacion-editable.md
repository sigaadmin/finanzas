# Planeación Editable Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Materializar las cinco importaciones confirmadas en una propuesta canónica editable, calcular versiones inmutables y distribuir recortes hasta producir una propuesta ajustada conciliada.

**Architecture:** Las tablas de importación permanecen inmutables. `OwnRevenueProposal` es el agregado versionado; sus necesidades especializadas contienen referencias opcionales a la evidencia importada. Los borradores se editan, las versiones calculadas y ajustadas son snapshots inmutables, y la Hoja de trabajo/ABPRE se proyectan desde la propuesta.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Laravel 3, React 19, Wayfinder, Tailwind CSS 4, Pest 4, enteros en centavos y decimales fijos sin `float`.

---

## Reglas de ejecución

- Trabajar sin subagentes, conforme a la especificación de control de consumo.
- Antes de cada tarea consultar Laravel Boost con los paquetes afectados.
- Usar TDD: prueba roja, implementación mínima, prueba verde y commit.
- Ejecutar únicamente la prueba indicada durante el desarrollo.
- Ejecutar la suite completa una vez en Task 10.
- No instalar dependencias en este plan.
- No modificar ni borrar registros de importación confirmados.

### Task 1: Persistir el agregado versionado de planeación

**Files:**
- Create: `app/Enums/Finance/OwnRevenue/OwnRevenueProposalStatus.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposal.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalTechnicalNeed.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalFuelNeed.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalTravelCommission.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalTravelParticipant.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenuePlanningCorrection.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueRoute.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueTravelDestination.php`
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueTravelRate.php`
- Create factories for every model under `database/factories/Finance/OwnRevenue/Planning/`
- Create migrations for the nine tables under `database/migrations/`
- Modify: `app/Models/Finance/OwnRevenue/OwnRevenueBudget.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/OwnRevenueProposalSchemaTest.php`

- [ ] **Step 1: Generate models, migrations, enum and test**

Run the relevant `php artisan make:model ... -mf --no-interaction`, `make:class`, and:

```bash
php artisan make:test --pest Finance/OwnRevenue/Planning/OwnRevenueProposalSchemaTest --no-interaction
```

- [ ] **Step 2: Write the failing schema test**

The test must create a budget, proposal, one record of every specialized type, a participant, route, destination, rate and correction. Assert:

```php
expect($proposal->status)->toBe(OwnRevenueProposalStatus::Draft)
    ->and($proposal->technicalNeeds)->toHaveCount(1)
    ->and($proposal->fuelNeeds)->toHaveCount(1)
    ->and($proposal->travelCommissions->sole()->participants)->toHaveCount(1)
    ->and($proposal->corrections->sole()->old_value)->toBe('10.0000')
    ->and($proposal->corrections->sole()->new_value)->toBe('12.0000');
```

Also assert that two proposal versions may share the same `stable_key`, while the pair `(proposal_id, stable_key)` is unique per specialized table.

- [ ] **Step 3: Verify RED**

Run:

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/OwnRevenueProposalSchemaTest.php
```

Expected: FAIL because the enum and tables do not exist.

- [ ] **Step 4: Implement the schema**

Use these contracts:

```php
enum OwnRevenueProposalStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Adjusted = 'adjusted';
}
```

`own_revenue_proposals` must include budget, monotonically increasing version number, status, optional `based_on_proposal_id`, five explicit source file IDs, 64-character source fingerprint, total cents, creator/calculator metadata and timestamps. Unique `(own_revenue_budget_id, version_number)` and index `(own_revenue_budget_id, status)`.

Specialized rows must include `stable_key`, proposal/budget/activity, optional import record origin, `sort_order`, editable fields from the approved design and timestamps. Store decimals as strings in decimal columns with explicit scale. Technical totals and all derived monetary outputs use integer cents.

`own_revenue_planning_corrections` must be polymorphic and include field, old/new value strings, justification, actor and correction timestamp.

Routes, destinations and rates are budget-scoped reusable catalogs. Destination contains normalized destination plus food/lodging zones; rate contains normalized position, zones and UMA multipliers.

- [ ] **Step 5: Add typed models, casts, relations and factories**

Every model must use `#[Fillable]`, explicit casts, typed relationship returns and no business logic in attribute mutators. Add relations from `OwnRevenueBudget`:

```php
public function proposals(): HasMany;
public function planningRoutes(): HasMany;
public function travelDestinations(): HasMany;
public function travelRates(): HasMany;
```

- [ ] **Step 6: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/OwnRevenueProposalSchemaTest.php
git add app/Enums/Finance/OwnRevenue app/Models/Finance/OwnRevenue/Planning database/factories/Finance/OwnRevenue/Planning database/migrations app/Models/Finance/OwnRevenue/OwnRevenueBudget.php tests/Feature/Finance/OwnRevenue/Planning/OwnRevenueProposalSchemaTest.php
git commit -m "feat: store versioned own revenue proposals"
```

### Task 2: Validar y materializar la propuesta desde importaciones

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Planning/OwnRevenueProposalReadiness.php`
- Create: `app/Actions/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalFromImports.php`
- Create: `app/Http/Requests/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalRequest.php`
- Create: `app/Http/Controllers/Finance/OwnRevenueProposalCreationController.php`
- Modify: `app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalFromImportsTest.php`

- [ ] **Step 1: Generate files and write the failing test**

Generate with Artisan. Build a fixture with five current confirmed files, fully reconciled supporting rows, confirmed COG, reviewed UMA/fuel, institutional data and signatories. POST the creation endpoint and assert:

```php
expect($proposal->status)->toBe(OwnRevenueProposalStatus::Draft)
    ->and($proposal->technicalNeeds)->toHaveCount($confirmedTechnicalCount)
    ->and($proposal->fuelNeeds)->toHaveCount($confirmedFuelCount)
    ->and($proposal->source_fingerprint)->toHaveLength(64);
```

Assert every copied row references its import origin, imported rows remain byte-for-byte unchanged, repeated submission with the same expected source IDs does not create a duplicate draft, and a stale source rolls back all inserts.

Add readiness cases for missing format, pending activity, import issue, unconfirmed COG, annual parameter and signatory.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalFromImportsTest.php
```

Expected: FAIL because the route and readiness service do not exist.

- [ ] **Step 3: Implement readiness**

Expose:

```php
public function forBudget(OwnRevenueBudget $budget): OwnRevenueProposalReadinessResult;
```

The result contains `ready`, current five file IDs, canonical fingerprint and a list of user-facing blockers. It must use the same definition of current confirmed version as activity reconciliation: highest `version_number`, then `id`.

- [ ] **Step 4: Implement transactional materialization**

```php
public function handle(
    OwnRevenueBudget $budget,
    User $user,
    array $expectedFileIds,
    string $expectedFingerprint,
): OwnRevenueProposal;
```

Authorize `createProposal`, lock budget/files, recompute readiness, reject stale observations, allocate the next version number, copy current supporting rows, and group imported travel rows into commissions by normalized date/month/reason/destination with participants beneath them. Seed reusable routes, destinations and rates from distinct confirmed values without overwriting existing reviewed catalog entries.

- [ ] **Step 5: Implement request, route and controller**

POST route:

```text
finance/own-revenue/budgets/{budget}/proposals/from-imports
finance.own-revenue.budgets.proposals.from-imports.store
```

Validate five positive file IDs and a 64-character fingerprint. Return to the proposal page with `Propuesta creada desde las importaciones confirmadas.`

Policy: Owner/Admin/FinanceManager may create. FinanceAssistant/Auditor may not.

- [ ] **Step 6: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalFromImportsTest.php
git add app/Services/Finance/OwnRevenue/Planning app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance/OwnRevenueProposalCreationController.php app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php routes/web.php tests/Feature/Finance/OwnRevenue/Planning/CreateOwnRevenueProposalFromImportsTest.php
git commit -m "feat: create planning drafts from confirmed imports"
```

### Task 3: Encapsular cálculos monetarios y decimales

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Planning/FixedDecimal.php`
- Create: `app/Services/Finance/OwnRevenue/Planning/TechnicalNeedCalculator.php`
- Create: `app/Services/Finance/OwnRevenue/Planning/FuelNeedCalculator.php`
- Create: `app/Services/Finance/OwnRevenue/Planning/TravelCommissionCalculator.php`
- Test: `tests/Unit/Finance/OwnRevenue/Planning/PlanningCalculatorsTest.php`

- [ ] **Step 1: Write calculator tests first**

Cover:

```php
expect($technical->referenceCents('2.5', '100.25'))->toBe('25063');
expect($fuel->calculate('83.3', '10', '24.50')->budgetedCents)->toBe('25000');
expect($fuel->calculate('100', '10', '25.00')->budgetedCents)->toBe('25000');
expect($travel->calculate('2', '10', '8', '117.31', '0')->totalCents)->toBeString();
```

Add invalid scale, division by zero, negative input, overflow and exact-multiple cases. Do not cast inputs to `float` in tests or production.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Unit/Finance/OwnRevenue/Planning/PlanningCalculatorsTest.php
```

- [ ] **Step 3: Implement fixed decimal primitives**

`FixedDecimal` must parse signed decimal strings into scaled integer strings and expose `add`, `subtract`, `multiply`, `divideCeiling`, `roundHalfUp`, `roundCentsUpToPeso` and `roundCentsUpToMultiple(5000)`. Return immutable result DTOs from each specialized calculator. Technical reference cents use half-up rounding; fuel follows the two upward-rounding stages approved in the specification.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/Finance/OwnRevenue/Planning/PlanningCalculatorsTest.php
git add app/Services/Finance/OwnRevenue/Planning tests/Unit/Finance/OwnRevenue/Planning/PlanningCalculatorsTest.php
git commit -m "feat: calculate planning amounts with fixed precision"
```

### Task 4: Editar conceptos de Ficha técnica

**Files:**
- Create: `app/Actions/Finance/OwnRevenue/Planning/StoreProposalTechnicalNeed.php`
- Create: `app/Actions/Finance/OwnRevenue/Planning/DeleteProposalTechnicalNeed.php`
- Create Form Requests and controllers under `app/Http/Requests/Finance/OwnRevenue/Planning/` and `app/Http/Controllers/Finance/`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTechnicalNeedsTest.php`

- [ ] **Step 1: Write failing HTTP/domain tests**

Assert create/update/delete in Draft, activity and classification scoped to the budget, region forced to `02-001`, reference calculation, editable total, correction audit when total differs, stable origin preservation and rejection for Calculated/Adjusted proposals. FinanceAssistant may edit; Auditor may not.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTechnicalNeedsTest.php
```

- [ ] **Step 3: Implement policy and actions**

Add `editProposal` to the policy: Draft only; Owner/Admin/FinanceManager/FinanceAssistant allowed. Actions must lock proposal and row, validate proposal/budget ownership, calculate reference cents, require `override_justification` when definitive total differs, append a correction, and update only the draft row.

Use POST/PUT/DELETE routes below `budgets/{budget}/proposals/{proposal}/technical-needs` with scoped model binding.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTechnicalNeedsTest.php
git add app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance routes/web.php app/Policies/Finance/OwnRevenue/OwnRevenueBudgetPolicy.php tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTechnicalNeedsTest.php
git commit -m "feat: edit technical needs in planning drafts"
```

### Task 5: Editar recorridos y necesidades de Combustible

**Files:**
- Create CRUD actions/requests/controllers for `OwnRevenueRoute` and `OwnRevenueProposalFuelNeed`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/ManageProposalFuelNeedsTest.php`

- [ ] **Step 1: Write failing tests**

Assert catalog route reuse, point correction with justification, operational month preservation, budget month forced from `fuel_budget_month`, price default from budget, all calculator outputs persisted, exact $50 behavior, reorder/delete, stale draft rejection and permissions.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalFuelNeedsTest.php
```

- [ ] **Step 3: Implement transactions and routes**

The store action contract is:

```php
public function handle(
    OwnRevenueProposal $proposal,
    User $user,
    FuelNeedData $data,
    ?OwnRevenueProposalFuelNeed $need = null,
): OwnRevenueProposalFuelNeed;
```

Recompute every derived value server-side. Never accept mathematical, rounded or budgeted totals from the client as authoritative. Require justification when route distance, yield, fuel price or calculated budget is overridden.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalFuelNeedsTest.php
git add app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance routes/web.php tests/Feature/Finance/OwnRevenue/Planning/ManageProposalFuelNeedsTest.php
git commit -m "feat: edit fuel needs in planning drafts"
```

### Task 6: Editar comisiones y participantes de Viáticos

**Files:**
- Create CRUD actions/requests/controllers for destinations, rates, commissions and participants
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTravelCommissionsTest.php`

- [ ] **Step 1: Write failing tests**

Assert destination selects food/lodging zones, normalized position selects rate with fallback `Puestos no considerados en los anteriores`, budget UMA is used, multiple participants aggregate exactly, flight is added once at commission level, manual zone/rate override requires justification, and immutable/stale/authorization rules hold.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTravelCommissionsTest.php
```

- [ ] **Step 3: Implement server-side calculation**

Commission totals must be recomputed after each participant mutation inside the same transaction. Store applied UMA/rate/zone snapshots on participant rows so later catalog changes do not rewrite an existing version.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTravelCommissionsTest.php
git add app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance routes/web.php tests/Feature/Finance/OwnRevenue/Planning/ManageProposalTravelCommissionsTest.php
git commit -m "feat: edit travel commissions in planning drafts"
```

### Task 7: Exponer el espacio de Planeación mediante Inertia

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Planning/OwnRevenuePlanningViewData.php`
- Create: `app/Http/Controllers/Finance/OwnRevenuePlanningController.php`
- Create: `resources/js/pages/finance/own-revenue/planning/show.tsx`
- Create focused editor components under `resources/js/components/finance/own-revenue/planning/`
- Modify: `resources/js/pages/finance/own-revenue/budgets/show.tsx`
- Modify: `resources/js/types/finance-own-revenue.ts`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/OwnRevenuePlanningNavigationTest.php`
- Test: `tests/Frontend/own-revenue-planning-state.test.mjs`

- [ ] **Step 1: Write failing backend and pure frontend tests**

Assert the page returns readiness when no proposal exists; current proposal, summaries and paginated rows when one exists; mutation permissions by role; version selection; no records from another budget; and same-tab query helpers for `section`, `page` and selected detail.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/OwnRevenuePlanningNavigationTest.php
node --test tests/Frontend/own-revenue-planning-state.test.mjs
```

- [ ] **Step 3: Implement read model and page**

The page must show a prerequisite checklist, `Crear propuesta desde importaciones`, tabs for the three domains, totals, source labels, server pagination, forms only for Draft with edit permission, correction modal, and version history. No `target="_blank"` or technical variable names.

- [ ] **Step 4: Generate Wayfinder and verify**

```bash
php artisan wayfinder:generate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/OwnRevenuePlanningNavigationTest.php
node --test tests/Frontend/own-revenue-planning-state.test.mjs
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/planning resources/js/components/finance/own-revenue/planning
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Finance/OwnRevenue/Planning/OwnRevenuePlanningViewData.php app/Http/Controllers/Finance/OwnRevenuePlanningController.php resources/js/pages/finance/own-revenue/planning resources/js/components/finance/own-revenue/planning resources/js/pages/finance/own-revenue/budgets/show.tsx resources/js/types/finance-own-revenue.ts routes/web.php tests/Feature/Finance/OwnRevenue/Planning/OwnRevenuePlanningNavigationTest.php tests/Frontend/own-revenue-planning-state.test.mjs
git commit -m "feat: manage editable own revenue planning"
```

### Task 8: Calcular y congelar una propuesta

**Files:**
- Create: `app/Services/Finance/OwnRevenue/Planning/OwnRevenueProposalProjector.php`
- Create: `app/Services/Finance/OwnRevenue/Planning/OwnRevenueProposalFingerprint.php`
- Create: `app/Actions/Finance/OwnRevenue/Planning/CalculateOwnRevenueProposal.php`
- Create request/controller/route
- Test: `tests/Feature/Finance/OwnRevenue/Planning/CalculateOwnRevenueProposalTest.php`

- [ ] **Step 1: Write failing tests**

Assert projections by activity/part/month, region `02-001`, exact annual totals, immutable Calculated status, calculator snapshots, current source fingerprint, status transition to `proposal_calculated`, creation of a new draft based on a calculated version, and rollback for invalid/stale data.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/CalculateOwnRevenueProposalTest.php
```

- [ ] **Step 3: Implement projection and calculation**

The projector returns canonical arrays for Work Sheet and ABPRE summaries. The action locks budget/proposal, authorizes `calculateProposal`, validates every row and catalog reference, computes fingerprint and totals, marks the draft Calculated, records actor/time, and moves the budget to `ProposalCalculated`.

The `create revision` action copies a Calculated or Adjusted version into the next Draft without changing its source version.

- [ ] **Step 4: Verify and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/CalculateOwnRevenueProposalTest.php
git add app/Services/Finance/OwnRevenue/Planning app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance routes/web.php tests/Feature/Finance/OwnRevenue/Planning/CalculateOwnRevenueProposalTest.php
git commit -m "feat: calculate immutable proposal versions"
```

### Task 9: Distribuir recortes y crear la propuesta ajustada

**Files:**
- Create: `app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalCut.php`
- Create migration/factory
- Create: `app/Services/Finance/OwnRevenue/Planning/ProportionalCutSuggestion.php`
- Create: `app/Actions/Finance/OwnRevenue/Planning/StoreProposalCut.php`
- Create: `app/Actions/Finance/OwnRevenue/Planning/CreateAdjustedOwnRevenueProposal.php`
- Create requests/controllers/routes
- Create: `resources/js/pages/finance/own-revenue/planning/cuts.tsx`
- Test: `tests/Feature/Finance/OwnRevenue/Planning/AdjustOwnRevenueProposalTest.php`
- Test: `tests/Frontend/own-revenue-cuts-state.test.mjs`

- [ ] **Step 1: Write failing domain and HTTP tests**

Assert required cut from Calculated versus current ABPRE, manual cuts per stable need, no negative/over-cut, suggestion sums exactly with deterministic remainder distribution, suggestion is read-only until confirmed, stale ABPRE rejection, exact per-part/month reconciliation, separate Adjusted snapshot, budget status `ProposalAdjusted`, and prior snapshot immutability.

- [ ] **Step 2: Verify RED**

```bash
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/AdjustOwnRevenueProposalTest.php
node --test tests/Frontend/own-revenue-cuts-state.test.mjs
```

- [ ] **Step 3: Implement deterministic suggestion and actions**

Use integer cents and calculate each required reduction independently by activity, partida and month. Within each compatible projection key, allocate floor-proportional amounts, then distribute remaining cents by largest fractional remainder and stable key. Persist cuts only through explicit POST. `CreateAdjustedOwnRevenueProposal` copies specialized rows to a new version, applies reductions, recalculates projections and refuses completion unless every reconciliation equals zero.

- [ ] **Step 4: Implement the cuts page**

Show calculated, required, distributed, pending, adjusted and ABPRE totals; filters by format/activity/item/month; manual inputs; suggestion preview; explicit apply; and no mutation controls for consultation roles.

- [ ] **Step 5: Verify and commit**

```bash
php artisan wayfinder:generate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Finance/OwnRevenue/Planning/AdjustOwnRevenueProposalTest.php
node --test tests/Frontend/own-revenue-cuts-state.test.mjs
npm run types:check
git add app/Models/Finance/OwnRevenue/Planning/OwnRevenueProposalCut.php database/migrations database/factories/Finance/OwnRevenue/Planning app/Services/Finance/OwnRevenue/Planning app/Actions/Finance/OwnRevenue/Planning app/Http/Requests/Finance/OwnRevenue/Planning app/Http/Controllers/Finance resources/js/pages/finance/own-revenue/planning/cuts.tsx routes/web.php tests/Feature/Finance/OwnRevenue/Planning/AdjustOwnRevenueProposalTest.php tests/Frontend/own-revenue-cuts-state.test.mjs
git commit -m "feat: distribute proposal cuts"
```

### Task 10: Verificar el incremento de Planeación editable

**Files:**
- Modify only when a failing assertion reveals a defect.

- [ ] **Step 1: Run formatting and the complete verification once**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run test:frontend
npm run types:check
npx eslint resources/js/pages/finance/own-revenue/planning resources/js/components/finance/own-revenue/planning resources/js/pages/finance/own-revenue/budgets/show.tsx
npm run build
git diff --check
```

Expected: all commands pass; skipped tests may remain only when already documented.

- [ ] **Step 2: Verify representative real data without mutation**

For budget 1, compare the materialization preview against the confirmed baseline of 122 technical needs, 156 fuel plans and 67 travel rows. Verify totals and readiness through read-only queries or a transaction rolled back at the end. Do not create a persistent proposal merely for verification.

- [ ] **Step 3: Record the checkpoint**

```bash
git status -sb
git log --oneline --decorate -12
```

The branch must be clean. Record the last commit, test counts and that the next plan is `2026-07-14-cierre-fase-3.md`.
