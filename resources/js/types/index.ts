export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: string;
    created_at: string;
    updated_at: string;
}

export interface Transaction {
    id: number;
    upload_id: number;
    source_row: number;
    row_type: string;
    ledger_year: number;
    ledger_month: number;
    obr_prefix: string | null;
    obr_no: string | null;
    payee: string | null;
    charging_code: string | null;
    rc_code: string | null;
    particulars: string | null;
    status: string | null;
    tax_comp: string | null;
    tax_evat: string | null;
    tax_vt: string | null;
    tax_pt: string | null;
    tax_final: string | null;
    local_tax: string | null;
    refund_liquidated: string | null;
    retention: string | null;
    charging_breakdown: string | null;
    gross_amount: string | null;
    net_amount: string | null;
    receipts: string | null;
    payment_date: string | null;
    dv_prefix: string | null;
    dv_no: string | null;
    acct_code: string | null;
    jev_no: string | null;
    remarks: string | null;
    payment_mode: string | null;
    extras: string;
    raw_row: string;
    created_at: string;
}
