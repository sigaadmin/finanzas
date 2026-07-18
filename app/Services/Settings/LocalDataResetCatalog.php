<?php

namespace App\Services\Settings;

use App\Enums\Settings\LocalDataResetScope;
use Illuminate\Support\Collection;

class LocalDataResetCatalog
{
    /** @var list<string> */
    private const VENTANILLA_TABLES = [
        'receipt_cancellations',
        'seq_deposits',
        'receipts',
        'payment_transactions',
        'payment_procedure_items',
        'payment_procedures',
        'student_snapshots',
        'seq_report_exports',
    ];

    /** @var list<string> */
    private const U300_TABLES = [
        'u300_technical_sheets',
        'u300_budget_movements',
        'u300_budget_lines',
        'u300_requested_items',
        'u300_actions',
        'u300_goals',
        'u300_projects',
        'u300_budget_versions',
        'u300_programs',
    ];

    /** @var list<string> */
    private const OWN_REVENUE_TABLES = [
        'own_revenue_expense_dossier_requirements',
        'own_revenue_expense_dossier_documents',
        'own_revenue_expense_dossier_transitions',
        'own_revenue_fuel_commissions',
        'own_revenue_fuel_funds',
        'own_revenue_expense_dossiers',
        'own_revenue_expense_requirement_rules',
        'own_revenue_budget_modifications',
        'own_revenue_modified_budget_lines',
        'own_revenue_workbook_exports',
        'own_revenue_initial_budgets',
        'own_revenue_proposal_travel_participants',
        'own_revenue_proposal_travel_commissions',
        'own_revenue_proposal_fuel_needs',
        'own_revenue_proposal_technical_needs',
        'own_revenue_proposal_cuts',
        'own_revenue_planning_corrections',
        'own_revenue_proposals',
        'own_revenue_activity_assignments',
        'own_revenue_activity_rules',
        'own_revenue_travel_rates',
        'own_revenue_travel_destinations',
        'own_revenue_routes',
        'own_revenue_travel_commissions',
        'own_revenue_fuel_plans',
        'own_revenue_technical_sheet_needs',
        'own_revenue_work_sheet_months',
        'own_revenue_work_sheet_lines',
        'own_revenue_abpre_months',
        'own_revenue_abpre_justifications',
        'own_revenue_abpre_lines',
        'own_revenue_import_decisions',
        'own_revenue_import_origins',
        'own_revenue_import_issues',
        'own_revenue_import_rows',
        'own_revenue_import_files',
        'own_revenue_import_sessions',
        'own_revenue_signatories',
        'own_revenue_activities',
        'own_revenue_budgets',
    ];

    /** @var list<string> */
    private const GENERAL_TABLES = [
        'charge_concept_official_links',
        'official_fee_concepts',
        'official_fee_schedules',
        'charge_concepts',
        'expense_classifications',
        'finance_folio_sequences',
        'passkeys',
        'password_reset_tokens',
        'sessions',
        'authorized_accesses',
        'users',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * @return list<string>
     */
    public function tablesFor(LocalDataResetScope $scope): array
    {
        return match ($scope) {
            LocalDataResetScope::Ventanilla => self::VENTANILLA_TABLES,
            LocalDataResetScope::U300 => self::U300_TABLES,
            LocalDataResetScope::OwnRevenue => self::OWN_REVENUE_TABLES,
            LocalDataResetScope::All => [
                ...self::OWN_REVENUE_TABLES,
                ...self::U300_TABLES,
                ...self::VENTANILLA_TABLES,
                ...self::GENERAL_TABLES,
            ],
        };
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    public function fileRootsFor(LocalDataResetScope $scope): array
    {
        $u300 = [
            ['disk' => 'local', 'path' => 'u300/imports'],
            ['disk' => 'public', 'path' => 'u300/technical-sheets/reference-photos'],
        ];
        $ownRevenue = [
            ['disk' => 'local', 'path' => 'own-revenue/imports'],
            ['disk' => 'local', 'path' => 'own-revenue/exports'],
            ['disk' => 'local', 'path' => 'finance/own-revenue'],
        ];

        return match ($scope) {
            LocalDataResetScope::Ventanilla => [],
            LocalDataResetScope::U300 => $u300,
            LocalDataResetScope::OwnRevenue => $ownRevenue,
            LocalDataResetScope::All => [
                ...$u300,
                ...$ownRevenue,
                ['disk' => 'local', 'path' => 'finance/expense-classifications/imports'],
            ],
        };
    }

    /**
     * @return Collection<int, string>
     */
    public function applicationTables(): Collection
    {
        return collect($this->tablesFor(LocalDataResetScope::All))->unique()->values();
    }
}
