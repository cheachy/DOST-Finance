export interface LedgerRow {
    source_row: number;
    row_type?: string;
    month: number | null;
    obr: string | null;
    payee: string | null;
    charging: string | null;
    rc: string | null;
    particulars: string | null;
    status: string | null;
    status_flag: string | null;
    gross: string | null;
    net: string | null;
    dv: string | null;
    payment_mode: string | null;
    pay_date: string | null;
}

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}
