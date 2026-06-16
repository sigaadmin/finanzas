# Portal Financiero CREN Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the CREN financial portal for controlled payment procedures, internal receipts, external SEQ receipts, and monthly SEQ reporting.

**Architecture:** Laravel owns authentication, authorization, business rules, folios, receipt generation, reporting, and validation URLs. Inertia React provides hierarchical operational screens: dashboard, filtered lists, details, print surfaces, and report exports. SIGA2 student lookup is isolated behind a client/service so tests can fake it and production can use the internal token API.

**Tech Stack:** Laravel 13, Fortify, Inertia Laravel 3, Inertia React 3, Wayfinder, Tailwind CSS 4, Pest 4, SQLite for local tests, MySQL-compatible production schema.

---

## Scope Notes

The specification covers several related but separable subsystems. Implement them in this order so each checkpoint is testable and useful on its own:

1. Access and roles.
2. Finance domain schema and catalog.
3. SIGA2 student lookup boundary.
4. Payment procedure and payment registration.
5. Receipt generation and validation.
6. Report views and Excel export.
7. Hierarchical UI polish and verification.

Excel export requires a real XLSX writer. Adding a package such as PhpSpreadsheet or Laravel Excel needs user approval before implementation, because this project forbids dependency changes without approval.

## File Map

- `config/creN.php` or `config/finance.php`: institutional owner email, receipt folio prefixes, validation settings, SIGA2 base URL/token keys.
- `database/migrations/*`: authorized accesses, concepts, student snapshots, payment procedures, items, transactions, receipts, receipt cancellations, SEQ report export logs.
- `app/Enums/*`: roles, concept type, procedure status, transaction status, receipt type, receipt status.
- `app/Models/*`: `AuthorizedAccess`, `ChargeConcept`, `StudentSnapshot`, `PaymentProcedure`, `PaymentProcedureItem`, `PaymentTransaction`, `Receipt`, `ReceiptCancellation`, `SeqReportExport`.
- `app/Actions/Finance/*`: focused business actions for creating procedures, registering payments, generating receipts, cancelling receipts, and building reports.
- `app/Services/Siga/SigaStudentClient.php`: SIGA2 student search/get boundary.
- `app/Services/Finance/FolioService.php`: unique folio generation.
- `app/Services/Finance/MoneyToWords.php`: Spanish amount-in-words conversion.
- `app/Http/Controllers/Finance/*`: Inertia page controllers and write endpoints.
- `app/Http/Requests/Finance/*`: validation and authorization for catalog, procedure, payment, cancellation, and report export.
- `app/Policies/*`: server-side authorization for concepts, procedures, receipts, and reports.
- `routes/web.php`: protected internal finance routes and public limited validation route.
- `resources/js/pages/finance/*`: hierarchical screens for dashboard, concepts, procedures, receipts, reports, and validation.
- `resources/js/components/finance/*`: reusable finance table/filter/detail/receipt components.
- `resources/js/types/finance.ts`: typed DTOs for Inertia props.
- `tests/Feature/Finance/*`: feature coverage for auth, catalog, procedures, receipts, reports, validation, and navigation.

## Task 1: Access Model And Role Foundation

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_authorized_accesses_table.php`
- Create: `app/Models/AuthorizedAccess.php`
- Create: `app/Enums/UserRole.php`
- Modify: `app/Models/User.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Finance/AuthorizedAccessTest.php`

- [ ] Add `authorized_accesses` with normalized unique email, role, `is_active`, `last_used_at`, and timestamps.
- [ ] Seed `administrador.siga@crenfcp.edu.mx` as active `owner`.
- [ ] Add role helpers to `User`: owner, admin, finance manager, assistant, auditor.
- [ ] Ensure inactive or missing access cannot operate finance routes.
- [ ] Test owner seed, access normalization, active/inactive behavior, and role checks.
- [ ] Run `php artisan test --compact tests/Feature/Finance/AuthorizedAccessTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 2: Finance Domain Schema, Enums, Models, Factories

**Files:**
- Create: migrations for `charge_concepts`, `student_snapshots`, `payment_procedures`, `payment_procedure_items`, `payment_transactions`, `receipts`, `receipt_cancellations`, `seq_report_exports`
- Create: `app/Enums/Finance/ChargeConceptType.php`
- Create: `app/Enums/Finance/ChargeConceptStatus.php`
- Create: `app/Enums/Finance/PaymentProcedureStatus.php`
- Create: `app/Enums/Finance/PaymentTransactionStatus.php`
- Create: `app/Enums/Finance/ReceiptType.php`
- Create: `app/Enums/Finance/ReceiptStatus.php`
- Create: models and factories for each finance entity
- Test: `tests/Feature/Finance/FinanceSchemaTest.php`

- [ ] Define MySQL-compatible tables with integer pesos for money values.
- [ ] Store concept name, type, price, active status, and an immutable snapshot in procedure items.
- [ ] Store student name, matricula/SIGA id, grade/group/program snapshots for receipts.
- [ ] Model internal and external receipts in one `receipts` table using `type`, with external receipts linked to one procedure item.
- [ ] Add relationships and casts for enums, dates, and money values.
- [ ] Test relationships, enum casts, and immutable snapshots.
- [ ] Run `php artisan test --compact tests/Feature/Finance/FinanceSchemaTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 3: Charge Concept Catalog

**Files:**
- Create: `app/Http/Controllers/Finance/ChargeConceptController.php`
- Create: `app/Http/Requests/Finance/StoreChargeConceptRequest.php`
- Create: `app/Http/Requests/Finance/UpdateChargeConceptRequest.php`
- Create: `app/Policies/ChargeConceptPolicy.php`
- Create: `resources/js/pages/finance/concepts/index.tsx`
- Create: `resources/js/pages/finance/concepts/form.tsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/ChargeConceptManagementTest.php`

- [ ] Build list/create/edit/activate/inactivate routes for concepts.
- [ ] Require `interno` or `externo` before a concept can be active.
- [ ] Prevent inactive concepts from appearing in new procedure selection.
- [ ] Add compact Inertia pages with filters by status and type.
- [ ] Test manager access, assistant denial for configuration, validation, and inactive behavior.
- [ ] Run `php artisan test --compact tests/Feature/Finance/ChargeConceptManagementTest.php`.
- [ ] Regenerate Wayfinder route/action files if routes changed.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 4: SIGA2 Student Lookup Boundary

**Files:**
- Create: `config/finance.php`
- Create: `app/Data/Finance/SigaStudentData.php`
- Create: `app/Services/Siga/SigaStudentClient.php`
- Create: `app/Http/Controllers/Finance/StudentLookupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Finance/SigaStudentLookupTest.php`

- [ ] Add config keys for SIGA2 base URL, token, timeout, and finance API version.
- [ ] Implement a client that searches students and returns normalized DTOs.
- [ ] Expose an authenticated internal lookup endpoint for the Inertia procedure form.
- [ ] Fake HTTP responses in tests for found, empty, and unavailable SIGA2 cases.
- [ ] Ensure unavailable SIGA2 blocks new procedure creation unless a future manual mode is approved.
- [ ] Run `php artisan test --compact tests/Feature/Finance/SigaStudentLookupTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 5: Payment Procedure Draft Flow

**Files:**
- Create: `app/Actions/Finance/CreatePaymentProcedure.php`
- Create: `app/Actions/Finance/UpdatePaymentProcedureItems.php`
- Create: `app/Http/Controllers/Finance/PaymentProcedureController.php`
- Create: `app/Http/Requests/Finance/StorePaymentProcedureRequest.php`
- Create: `app/Policies/PaymentProcedurePolicy.php`
- Create: `resources/js/pages/finance/procedures/index.tsx`
- Create: `resources/js/pages/finance/procedures/create.tsx`
- Create: `resources/js/pages/finance/procedures/show.tsx`
- Test: `tests/Feature/Finance/PaymentProcedureFlowTest.php`

- [ ] Build hierarchical procedure list, create, and detail pages.
- [ ] Allow one student and one or more active concepts per procedure.
- [ ] Calculate totals from selected concept snapshots.
- [ ] Persist student snapshot and concept snapshots at procedure creation.
- [ ] Keep paid procedures immutable.
- [ ] Preserve filters when navigating from list to detail and back.
- [ ] Test no-student, no-concept, inactive-concept, multi-concept totals, mixed internal/external concepts, and immutability after payment.
- [ ] Run `php artisan test --compact tests/Feature/Finance/PaymentProcedureFlowTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 6: Payment Registration, Folios, Internal Receipt, External SEQ Receipts

**Files:**
- Create: `app/Actions/Finance/RegisterPayment.php`
- Create: `app/Actions/Finance/GenerateReceiptsForTransaction.php`
- Create: `app/Services/Finance/FolioService.php`
- Create: `app/Services/Finance/MoneyToWords.php`
- Create: `app/Http/Requests/Finance/RegisterPaymentRequest.php`
- Modify: `app/Http/Controllers/Finance/PaymentProcedureController.php`
- Test: `tests/Feature/Finance/PaymentRegistrationReceiptGenerationTest.php`

- [ ] Register payment only from pending procedures.
- [ ] Generate one internal receipt for the full procedure total.
- [ ] Generate one external SEQ receipt for each external procedure item, with exact item amount.
- [ ] Do not generate external receipts for internal concepts.
- [ ] Assign unique non-reusable folios to internal and external receipts.
- [ ] Store amount in words for internal totals and external item amounts.
- [ ] Test one internal-only procedure, one external-only procedure, and one mixed procedure with two external items plus one internal item.
- [ ] Run `php artisan test --compact tests/Feature/Finance/PaymentRegistrationReceiptGenerationTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 7: Receipt Print Surfaces And Public Validation

**Files:**
- Create: `app/Http/Controllers/Finance/ReceiptController.php`
- Create: `app/Http/Controllers/Finance/ReceiptValidationController.php`
- Create: `app/Actions/Finance/BuildReceiptValidationToken.php`
- Create: `resources/js/pages/finance/receipts/index.tsx`
- Create: `resources/js/pages/finance/receipts/show.tsx`
- Create: `resources/js/pages/finance/receipts/print-internal.tsx`
- Create: `resources/js/pages/finance/receipts/print-external-seq.tsx`
- Create: `resources/js/pages/finance/receipts/verify.tsx`
- Test: `tests/Feature/Finance/ReceiptRenderingAndValidationTest.php`

- [ ] Build receipt index/detail with filters by folio, student, type, date, and status.
- [ ] Build internal print view with all concepts in the procedure.
- [ ] Build external SEQ print view matching the official original/copy layout and using exactly one concept.
- [ ] Add QR payload URL for public limited validation.
- [ ] Validation page should expose folio, status, institution, and minimal non-sensitive data.
- [ ] Test internal receipt rendering, external SEQ one-concept rendering, QR validation URL, and minimal validation data.
- [ ] Run `php artisan test --compact tests/Feature/Finance/ReceiptRenderingAndValidationTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 8: Cancellation Flow

**Files:**
- Create: `app/Actions/Finance/CancelReceipt.php`
- Create: `app/Http/Requests/Finance/CancelReceiptRequest.php`
- Modify: `app/Http/Controllers/Finance/ReceiptController.php`
- Test: `tests/Feature/Finance/ReceiptCancellationTest.php`

- [ ] Allow only owner, admin, and finance manager to cancel.
- [ ] Require a cancellation reason.
- [ ] Store responsible user, reason, and timestamp.
- [ ] Preserve folios and never reuse cancelled folios.
- [ ] Exclude cancelled external receipts from the SEQ report or mark them according to the approved export format.
- [ ] Test assistant denial, missing reason rejection, audit persistence, and report exclusion.
- [ ] Run `php artisan test --compact tests/Feature/Finance/ReceiptCancellationTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 9: Finance Reports And SEQ Filtered View

**Files:**
- Create: `app/Queries/Finance/ReceiptReportQuery.php`
- Create: `app/Http/Controllers/Finance/FinanceReportController.php`
- Create: `app/Http/Controllers/Finance/SeqMonthlyReportController.php`
- Create: `app/Http/Requests/Finance/SeqMonthlyReportRequest.php`
- Create: `resources/js/pages/finance/reports/index.tsx`
- Create: `resources/js/pages/finance/reports/seq-monthly.tsx`
- Test: `tests/Feature/Finance/FinanceReportsTest.php`

- [ ] Build general movement report with filters by date, student, concept, type, folio, and state.
- [ ] Build SEQ monthly report as a filtered view of external receipts only.
- [ ] Ensure the SEQ report uses external receipt amount, not combined procedure amount.
- [ ] Show totals by concept and total monthly amount.
- [ ] Preserve filters in URL query params.
- [ ] Test internal exclusion, cancelled exclusion, filtered totals, and URL filter persistence.
- [ ] Run `php artisan test --compact tests/Feature/Finance/FinanceReportsTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 10: Excel Export For SEQ

**Files:**
- Create: `app/Actions/Finance/ExportSeqMonthlyReport.php`
- Create: `app/Http/Controllers/Finance/SeqMonthlyReportExportController.php`
- Modify: `resources/js/pages/finance/reports/seq-monthly.tsx`
- Test: `tests/Feature/Finance/SeqMonthlyReportExportTest.php`

- [ ] Ask for approval before adding an XLSX dependency.
- [ ] Export exactly the same records and totals shown by the filtered SEQ report view.
- [ ] Use the SEQ-required Excel columns and formatting once the official spreadsheet format is provided.
- [ ] Store export metadata in `seq_report_exports`.
- [ ] Test exported row count, folios, exact amounts, totals, cancelled exclusion, and filter parity.
- [ ] Run `php artisan test --compact tests/Feature/Finance/SeqMonthlyReportExportTest.php`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 11: Hierarchical Navigation And Dashboard

**Files:**
- Modify: `resources/js/components/nav-main.tsx`
- Modify: `resources/js/pages/dashboard.tsx`
- Create: `resources/js/components/finance/FilterBar.tsx`
- Create: `resources/js/components/finance/StatusBadge.tsx`
- Create: `resources/js/components/finance/MoneyText.tsx`
- Create: `resources/js/components/finance/ReceiptTypeBadge.tsx`
- Test: `tests/Feature/Finance/FinanceNavigationTest.php`

- [ ] Add finance navigation entries visible only to allowed roles.
- [ ] Dashboard should show operational counts: pending procedures, paid today, receipts issued today, external receipts pending monthly report review.
- [ ] Keep list/detail/action hierarchy instead of one overloaded page.
- [ ] Use compact filters, tables, badges, and predictable actions.
- [ ] Test navigation visibility by role and dashboard data for authorized users.
- [ ] Run `php artisan test --compact tests/Feature/Finance/FinanceNavigationTest.php`.
- [ ] Run `npm run lint` if frontend linting is available.
- [ ] Run `vendor/bin/pint --dirty --format agent`.

## Task 12: End-To-End Verification And Documentation Sync

**Files:**
- Modify: `docs/especificacion-portal-financiero-cren.md`
- Test: full focused finance test suite

- [ ] Review implementation against every criterion in the specification.
- [ ] Update the specification only where actual implementation decisions clarify approved behavior.
- [ ] Run `php artisan test --compact tests/Feature/Finance`.
- [ ] Run `vendor/bin/pint --dirty --format agent`.
- [ ] Run frontend build or lint command used by the project.
- [ ] Use the in-app browser against the Herd URL from Laravel Boost `get_absolute_url` to verify dashboard, procedure creation, receipt detail, print views, validation page, and SEQ report hierarchy.
- [ ] Confirm no console errors with Laravel Boost `browser_logs`.

## Self-Review

- Spec coverage: tasks cover access, roles, catalog, SIGA2 lookup, procedures, payment registration, internal receipts, external unit SEQ receipts, QR validation, cancellation, reports, Excel export, and hierarchical UI.
- Placeholder scan: the only open dependency is XLSX implementation, explicitly blocked on approval because dependency changes require user approval.
- Type consistency: receipt terminology is `internal receipt` for full procedure and `external SEQ receipt` for one external concept.
