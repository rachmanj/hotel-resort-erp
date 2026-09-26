export interface StatusPayload {
    bank_net: number;
    book_net: number;
    difference: number;
    unmatched_bank_count: number;
    unmatched_book_count: number;
    deposits_in_transit: number;
    outstanding_checks: number;
    outstanding_bank_net: number;
    statement_opening: number | null;
    statement_closing: number | null;
    book_closing: number | null;
    adjusted_statement_balance: number;
    reconciliation_difference: number;
    unexplained_difference: number;
    cross_foot: boolean | null;
    is_balanced: boolean;
    incomplete: boolean;
    diagnostic: string;
    status?: string;
    status_label?: string;
    validation_status?: string | null;
    bank_lines_count?: number;
    book_lines_count?: number;
    match_groups_count?: number;
    stale_lines_count?: number;
    outstanding_attention_count?: number;
    rejection_reason?: string | null;
    submitted_at?: string | null;
    validated_at?: string | null;
}

export interface StatementLineRow {
    id: number;
    posting_date: string | null;
    description: string | null;
    reference: string | null;
    statement_line_ref: string | null;
    debit: number;
    credit: number;
    net_amount: number;
    running_balance: number | null;
    match_status: string;
    match_status_label: string;
    match_group_id: number | null;
    exclude_reason: string | null;
    is_ai_extracted: boolean;
    is_carried_forward: boolean;
    adjusting_journal_id: number | null;
    adjusting_journal_no: string | null;
    suggested_counter_account_code: string | null;
    suggested_counter_account_id: number | null;
    suggested_counter_account_name: string | null;
    can_adjust: boolean;
    is_manual_line: boolean;
}

export interface BookLineRow {
    id: number;
    posting_date: string | null;
    doc_num: string | null;
    reference: string | null;
    description: string | null;
    debit: number;
    credit: number;
    net_amount: number;
    match_status: string;
    match_status_label: string;
    match_group_id: number | null;
    exclude_reason: string | null;
    is_stale: boolean;
    stale_reason: string | null;
    is_carried_forward: boolean;
}

export interface MatchGroupRow {
    id: number;
    match_type: string;
    match_type_label: string;
    bank_total: number;
    book_total: number;
    difference: number;
    matched_by: string | null;
    matched_at: string | null;
    statement_line_ids: number[];
    book_line_ids: number[];
}

export interface SubmitChecklistItem {
    key: string;
    label: string;
    passed: boolean;
    detail?: string | null;
}

export interface PostableAccount {
    id: number;
    account_code: string;
    name: string;
    label: string;
}

export interface ReconciliationHeader {
    id: number;
    bank_name: string | null;
    account_no: string | null;
    period_end_date: string;
    periode: string | null;
    statement_opening_balance: number | null;
    statement_closing_balance: number;
    book_closing_balance: number;
    status: string;
    status_label: string;
    validation_status: string | null;
    validation_status_label: string | null;
    rejection_reason: string | null;
    prepared_by: string | null;
    prepared_at: string | null;
    submitted_by: string | null;
    submitted_at: string | null;
    validated_by: string | null;
    validated_at: string | null;
}
