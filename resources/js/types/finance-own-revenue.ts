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
    estimated_income_cents: number | null;
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
    estimated_income_cents: number | null;
    cut_percentage: string;
    uma_value: string;
    uma_status: AnnualValueStatus;
    fuel_price_per_liter: string;
    fuel_price_status: AnnualValueStatus;
    signatories: OwnRevenueSignatoryFormValue[];
};
