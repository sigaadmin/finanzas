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

export type ExpenseDossier = {
    id: number;
    folio: string;
    status:
        | 'draft'
        | 'sufficiency_requested'
        | 'sufficiency_confirmed'
        | 'purchase_in_progress'
        | 'payment_requested'
        | 'finance_authorized'
        | 'budget_office_authorized'
        | 'paid'
        | 'rejected'
        | 'cancelled';
    concept: string;
    amount_cents: string;
    purchase_responsibility: 'cren' | 'seq';
    external_reference: string | null;
    purchase_reference: string | null;
    payment_request_reference: string | null;
    finance_authorization_reference: string | null;
    budget_office_authorization_reference: string | null;
    payment_reference: string | null;
    notes: string | null;
    line: {
        specific_item_code: string;
        specific_item_name: string;
        month: number;
    };
    requested_by_name: string;
    sufficiency_requested_at: string | null;
    sufficiency_confirmed_at: string | null;
    purchase_started_at: string | null;
    payment_requested_at: string | null;
    finance_authorized_at: string | null;
    budget_office_authorized_at: string | null;
    paid_at: string | null;
    latest_transition: {
        from_status: ExpenseDossier['status'] | null;
        to_status: ExpenseDossier['status'];
        reason: string | null;
        actor_name: string;
        occurred_at: string | null;
    } | null;
    documents: Array<{
        id: number;
        original_name: string;
        mime_type: string;
        size_bytes: number;
        uploaded_at: string | null;
    }>;
};
