export type AnnualValueStatus = 'pending_review' | 'provisional' | 'final';

export type OwnRevenueBudgetStatus =
    | 'draft'
    | 'proposal_calculated'
    | 'proposal_adjusted'
    | 'initial_authorized'
    | 'in_execution'
    | 'closed';

export type CogCatalogStatus = 'pending_confirmation' | 'confirmed';

export type OwnRevenueIndexPermissions = {
    create: boolean;
};

export type OwnRevenueDetailPermissions = {
    updateSettings: boolean;
    copy: boolean;
    confirmCog: boolean;
    viewImports: boolean;
};

export type OwnRevenueBudgetListItem = {
    id: number;
    fiscal_year: number;
    status: OwnRevenueBudgetStatus;
    region: {
        code: string;
        name: string;
    };
    uma: { status: AnnualValueStatus };
    fuel: { status: AnnualValueStatus };
    cog: { status: CogCatalogStatus };
    created_at: string | null;
    updated_at: string | null;
};

export type OwnRevenueSourceBudget = {
    id: number;
    fiscal_year: number;
    status: OwnRevenueBudgetStatus;
};

export type OwnRevenueBudgetSettings = {
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
    cut_percentage: string | null;
    uma_value: string | null;
    uma_status: AnnualValueStatus;
    fuel_price_per_liter: string | null;
    fuel_price_status: AnnualValueStatus;
    fuel_budget_month: number;
};

export type OwnRevenueActivity = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
};

export type OwnRevenueSignatory = {
    id: number;
    role_key: string;
    name: string;
    position: string;
    academic_degree: string | null;
    sort_order: number;
};

export type OwnRevenueCogSummary = {
    row_count: number;
    source_year: number | null;
    status: CogCatalogStatus;
    confirmed_by: { id: number; name: string } | null;
    confirmed_at: string | null;
};

export type OwnRevenueBudgetDetail = {
    id: number;
    fiscal_year: number;
    status: OwnRevenueBudgetStatus;
    settings: OwnRevenueBudgetSettings;
    activities: OwnRevenueActivity[];
    signatories: OwnRevenueSignatory[];
    cog: OwnRevenueCogSummary;
    created_at: string | null;
    updated_at: string | null;
};

export type OwnRevenueSignatoryFormValue = {
    clientKey: string;
    role_key: string;
    name: string;
    position: string;
    academic_degree: string;
    sort_order: number;
};

export type OwnRevenueAnnualSettingsFormData = {
    institution_name: string;
    responsible_unit_code: string;
    responsible_unit_name: string;
    budget_program_code: string;
    budget_program_name: string;
    component_code: string;
    component_name: string;
    official_activity_code: string;
    official_activity_name: string;
    estimated_income_cents: string | null;
    cut_percentage: string;
    uma_value: string;
    uma_status: AnnualValueStatus;
    fuel_price_per_liter: string;
    fuel_price_status: AnnualValueStatus;
    signatories: OwnRevenueSignatoryFormValue[];
};

export type PlanningSection = 'technical' | 'fuel' | 'travel';
export type OwnRevenueProposalStatus = 'draft' | 'calculated' | 'adjusted';

export type PlanningBudget = {
    id: number;
    fiscal_year: number;
    status: OwnRevenueBudgetStatus;
    uma_value: string | null;
    fuel_price_per_liter: string | null;
    fuel_budget_month: number;
};

export type PlanningReadiness = {
    ready: boolean;
    blockers: string[];
    source_file_ids: Record<string, number>;
    source_fingerprint: string;
};

export type PlanningProposal = {
    id: number;
    version_number: number;
    status: OwnRevenueProposalStatus;
    total_amount_cents: string;
    created_at: string | null;
    calculated_at: string | null;
    fingerprint: string;
    sources: Record<string, string | null>;
};

export type PlanningVersion = {
    id: number;
    version_number: number;
    status: OwnRevenueProposalStatus;
    total_amount_cents: string;
    created_at: string | null;
};

export type PlanningSummary = {
    count: number;
    total_amount_cents: string;
};

export type PlanningActivityOption = {
    id: number;
    code: string;
    name: string;
};

export type PlanningRow = {
    id: number;
    title: string;
    activity: PlanningActivityOption;
    sort_order: number;
    source_label: string;
    has_corrections: boolean;
    description?: string;
    reason?: string;
    specific_item_code?: string;
    specific_item_name?: string;
    quantity?: string;
    unit?: string;
    operational_month?: number;
    budget_month: number;
    total_kilometers?: string;
    participants_count?: number;
    budget_amount_cents?: string;
    total_amount_cents?: string;
};

export type PlanningPaginator = {
    data: PlanningRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type PlanningCorrection = {
    id: number;
    field: string;
    old_value: string;
    new_value: string;
    justification: string;
    actor_name: string;
    corrected_at: string | null;
};

export type PlanningSelectedDetail = {
    id: number;
    title: string;
    corrections: PlanningCorrection[];
} | null;

export type PlanningCatalogs = {
    activities: PlanningActivityOption[];
    expense_classifications: Array<{
        id: number;
        specific_item_code: string;
        specific_item_name: string;
    }>;
    routes: Array<{
        id: number;
        origin: string;
        destination: string;
        one_way_kilometers: string;
        additional_kilometers: string;
    }>;
    destinations: Array<{
        id: number;
        destination: string;
        food_zone: number;
        lodging_zone: number;
    }>;
    rates: Array<{
        id: number;
        position: string;
        food_zone: number;
        lodging_zone: number;
        per_diem_uma: string;
        lodging_uma: string;
        is_fallback: boolean;
    }>;
};
