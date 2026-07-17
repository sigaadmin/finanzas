export type ExecutionBudget = {
    id: number;
    fiscal_year: number;
    status: 'initial_authorized' | 'in_execution' | 'closed';
    region_code: string;
    region_name: string;
};

export type ExecutionSummary = {
    initial_amount_cents: string;
    modified_amount_cents: string;
    reserved_amount_cents: string;
    committed_amount_cents: string;
    paid_amount_cents: string;
    available_amount_cents: string;
};

export type ExecutionLine = {
    id: number;
    chapter_code: string;
    chapter_name: string;
    specific_item_code: string;
    specific_item_name: string;
    month: number;
    initial_amount_cents: string;
    incoming_amount_cents: string;
    outgoing_amount_cents: string;
    modified_amount_cents: string;
    reserved_amount_cents: string;
    committed_amount_cents: string;
    paid_amount_cents: string;
    available_amount_cents: string;
};

export type ExecutionClassification = {
    id: number;
    chapter_code: string;
    chapter_name: string;
    specific_item_code: string;
    specific_item_name: string;
};

export type BudgetModification = {
    id: number;
    type: 'transfer' | 'rescheduling';
    amount_cents: string;
    reason: string;
    source: {
        specific_item_code: string;
        specific_item_name: string;
        month: number;
    };
    destination: {
        specific_item_code: string;
        specific_item_name: string;
        month: number;
    };
    recorded_by_name: string;
    recorded_at: string | null;
};
