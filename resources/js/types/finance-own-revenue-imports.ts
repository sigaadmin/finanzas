import type { OwnRevenueBudgetStatus } from '@/types/finance-own-revenue';

export type OwnRevenueImportFormat =
    | 'abpre'
    | 'work_sheet'
    | 'technical_sheet'
    | 'fuel'
    | 'travel_expenses';

export type OwnRevenueImportFileStatus =
    | 'uploaded'
    | 'analyzing'
    | 'needs_correction'
    | 'ready'
    | 'confirmed'
    | 'replaced'
    | 'discarded'
    | 'failed'
    | 'parser_pending';

export type OwnRevenueImportIssueSeverity = 'error' | 'warning' | 'info';
export type OwnRevenueImportResolution = 'manual' | 'xlsx' | 'custom';

export type OwnRevenueImportBudget = {
    id: number;
    fiscal_year: number;
    status: OwnRevenueBudgetStatus;
    institution_name: string;
    responsible_unit_code: string;
    responsible_unit_name: string;
    budget_program_code: string;
    budget_program_name: string;
    component_code: string;
    component_name: string;
    official_activity_code: string;
    official_activity_name: string;
    region_code: string;
    region_name: string;
    estimated_income_cents: string | null;
};

export type OwnRevenueImportSession = {
    id: number;
    status: 'open' | 'completed' | 'cancelled';
    created_at: string | null;
    completed_at: string | null;
};

export type OwnRevenueImportIssueCounts = {
    error: number;
    warning: number;
    info: number;
};

export type OwnRevenueImportFile = {
    id: number;
    name: string;
    size: number;
    format: OwnRevenueImportFormat | null;
    detected_format: OwnRevenueImportFormat | null;
    year: number | null;
    version: number;
    status: OwnRevenueImportFileStatus;
    confidence: number | null;
    analyzed: boolean;
    analyzed_at: string | null;
    confirmed: boolean;
    confirmed_at: string | null;
    can_reclassify: boolean;
    issue_counts: OwnRevenueImportIssueCounts;
};

export type OwnRevenueImportSlot = {
    format: OwnRevenueImportFormat;
    label: string;
    versions: OwnRevenueImportFile[];
    versions_total: number;
    versions_current_page: number;
    versions_last_page: number;
    versions_has_more: boolean;
    has_confirmed: boolean;
    has_parser_pending: boolean;
    latest_status: OwnRevenueImportFileStatus | null;
};

export type OwnRevenueImportIssueContext = Partial<{
    detected_year: number;
    fiscal_year: number;
    responsible_unit_code: string;
    specific_item_code: string;
    source_region: string;
    normalized_region: string;
    value: unknown;
    source_cents: string;
    calculated_cents: string;
}>;

export type OwnRevenueImportIssue = {
    id: number;
    severity: OwnRevenueImportIssueSeverity;
    code: string;
    field: string | null;
    message: string;
    context: OwnRevenueImportIssueContext;
};

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type LengthAwarePaginator<T> = {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginatorLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type OwnRevenueSelectedImportFile = OwnRevenueImportFile & {
    issues: LengthAwarePaginator<OwnRevenueImportIssue> & { has_more: boolean };
};

export type OwnRevenueAbprePreviewRow = {
    id: number;
    row_number: number;
    responsibleUnitCode?: string;
    responsibleUnitName?: string;
    budgetProgramCode?: string;
    budgetProgramName?: string;
    componentCode?: string;
    componentName?: string;
    officialActivityCode?: string;
    officialActivityName?: string;
    regionCode?: string;
    regionName?: string;
    sourceRegions?: Array<{ code: string; name: string }>;
    specificExpenseConceptCode?: string | null;
    specificItemCode?: string;
    months: Record<string, string>;
    annualAmountCents: string;
    sourceRows?: number[];
};

export type OwnRevenueImportPermissions = {
    upload: boolean;
    manage: boolean;
    confirm: boolean;
    download: boolean;
};

export type OwnRevenueImportWorkspaceProps = {
    budget: OwnRevenueImportBudget;
    session: OwnRevenueImportSession | null;
    slots: OwnRevenueImportSlot[];
    unassigned_files: OwnRevenueImportFile[];
    unassigned_files_meta: {
        total: number;
        current_page: number;
        last_page: number;
        has_more: boolean;
    };
    selected_file: OwnRevenueSelectedImportFile | null;
    preview_file: {
        id: number;
        name: string;
        version: number;
        status: OwnRevenueImportFileStatus;
        analyzed_at: string | null;
    } | null;
    preview: LengthAwarePaginator<OwnRevenueAbprePreviewRow>;
    decision_warnings: LengthAwarePaginator<OwnRevenueImportIssue> & {
        has_more: boolean;
    };
    permissions: OwnRevenueImportPermissions;
};

export type OwnRevenueImportDecision = {
    issue_id: number;
    resolution: OwnRevenueImportResolution;
    resolved_value: string | boolean | null;
    justification: string | null;
};
