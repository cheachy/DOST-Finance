<?php

// Column mapping confirmed against the real sample-master-ledger.xlsx
// If a future SL tab genuinely differs,
// its own sheet_templates.column_map should be built from scratch by
// the header-detection routine rather than forced into this shape.

return [

    // Sheets that are genuinely NOT subsidiary ledgers at all 

    'non_ledger_sheets' => [
        'Prior Year Retention', // no Charging/RC structure — accountant handles this one manually
        'Ref',                   // reference material, not a ledger
    ],

    // The SLs actively being imported RIGHT NOW. Everything else in
    // 'excluded_sheets' below (GREENWAVE, MOOE CNTG, SETUP CNTG,
    // TECHGROW, IFWD, SARAI, etc.) are REAL ledgers in the General
    // Ledger — they're just not in scope yet. Expanding scope later is
    // as simple as adding a sheet name here; since column/header
    // detection is already dynamic, most of them should work without
    // any other code change, as long as their layout is close enough
    // to PS/MOOE/GIA's confirmed structure.
    'active_scope_sheets' => [
        'MDS 101',
    ],

    // Real SLs, not yet in active scope — listed here (rather than
    // silently absent) so it's clear these are a deliberate "later"
    // decision, not an oversight.
    'deferred_sheets' => [
        'GREENWAVE',
        'GREENWAVE CNTG',
        'GREENWAVE pd AP',
        'MOOE CNTG',
        'SETUP CNTG',
        'TECHGROW CNTG',
        'TECHGROW PD AP',
        'IFWD CNTG',
        'IFWD CNTG pd AP',
        'SARAI MOOE CO CNTG',
        'SARAI CO CNTG  pd APs',
    ],

    // MDS 101 is the Master Ledger. It has the similar shape as the SLs
    // but is the definitive source of truth.
    'general_ledger_sheet' => null,

    // Cell holding the fiscal year for the whole file, e.g. "CY 2026"
    'fiscal_year_cell' => 'A3',

    // Header rows (combined) and first data row — confirmed on PS/MOOE/GIA.
    // These row NUMBERS are still assumed fixed (row 8 = primary label,
    // row 9 = secondary label) since that's a structural convention, not
    // a column position — a genuinely different tab would need its own
    // sheet_templates entry built by a human reviewing it, same as any
    // other real exception in this system.
    'header_row_primary' => 8,
    'header_row_secondary' => 9,
    'first_data_row' => 13,
    'data_row_offset' => 5, // first data row = detected header row + this offset (13 - 8 = 5, confirmed on PS/MOOE/GIA)

    // Label dictionary: normalized header text -> field name. The
    // parser scans row 8 + row 9, combines merged-cell groups, and
    // matches against THIS, rather than assuming fixed column letters.
    // Normalization = uppercase, trim, collapse whitespace/newlines,
    // strip # and / punctuation.
    //
    // Some labels are ambiguous alone and need the OTHER row's text
    // for context — those are listed as 'PRIMARY|SECONDARY' pairs.
    'header_dictionary' => [
        'OBR' => 'obr_number',              // spans 2 columns
        'PAYEE' => 'payee',
        'CHARGING' => 'charging_value',
        'RC' => 'rc',
        'PARTICULARS' => 'particulars',
        'W' => 'w',                          // also marks the START boundary of the tax zone — see tax_zone below
        // NOTE: individual tax columns (Comp, EVAT, VT, PT, Final Tax,
        // Checking, Local Tax, refund/liquidated damages, Retention,
        // Total Deductions) are DELIBERATELY not listed here one by
        // one. Tax categories change when government tax law changes —
        // hardcoding each one means a code change every time that
        // happens. Instead, everything between the 'w' column and
        // 'charging_breakdown' is scanned as an open-ended tax zone;
        // each column found there becomes (or matches) a row in
        // tax_types automatically, the same way a new SL becomes a
        // new row in accounts. See detectTaxZone() in the parser.
        'CHARGING BREAKDOWN' => 'charging_breakdown', // marks the END boundary of the tax zone
        'DUE TO OFFICERS AND EMPLOYEES/AP (NET)' => 'due_to_officers', // formula cell — its own transactions field, not a tax line
        'PAID NYDD|PRIOR YEAR' => 'paid_nydd_prior',
        'PAID APS|PRIOR YEAR' => 'paid_aps_prior',
        'PAID NYDD|CURRENT YEAR' => 'paid_nydd_curr',
        'PAID APS|CURRENT YEAR' => 'paid_aps_curr',
        'GROSS' => 'gross',
        'NET' => 'net',
        'DATE' => 'date',                     // standalone DATE only — see note on disambiguation below
        'DATE|FROM MMA' => 'processing_date_in',   // Phase 2, not yet imported
        'DATE|TO ORD' => 'processing_date_out',    // Phase 2, not yet imported
        'DV' => 'dv_number',                  // spans 2 columns
        'RECEIPTS' => 'receipts',
        'NET OF WTX' => 'payment_net_wtx',
        'BALANCE' => 'balance_ignore',         // present in source, never imported
        'ACCT CODES' => 'acct_code',
        'JEV' => 'jev_no',
        'REMARKS' => 'remarks',
        'ROD|A/C' => 'payment_mode',
        // Deliberately NOT mapped: the ROD summary columns themselves
        // (their headers are just 'PS'/'MOOE'/'CO' repeated under a
        // 'ROD' parent) — these are computed by the app, never read.
        // The detector must treat anything after the 'ROD' group
        // header as out of scope rather than trying to match 'PS' as
        // if it were a charging value.
    ],

    // Tax zone boundaries — everything strictly between these two
    // detected fields is scanned as open-ended tax columns. This is
    // what makes tax categories adapt automatically when tax law
    // changes, instead of needing a code change per new tax type.
    'tax_zone' => [
        'start_after_field' => 'w',
        'end_before_field' => 'charging_breakdown',
    ],

    // Known tax labels get a clean, stable code (so reports/exports
    // read nicely and stay consistent across imports) rather than a
    // raw slug. Anything encountered that ISN'T in this list still
    // gets auto-registered in tax_types with a generated code — it
    // just won't have a hand-picked name until someone adds it here.
    'known_tax_labels' => [
        'COMP' => 'vat_comp',                    // H: row9 only
        'EVAT' => 'vat_evat',                    // I: row9 only
        'VAT VT' => 'vat_vt',                    // J: row8='VAT' + row9='VT'
        'PT' => 'vat_pt',                        // K: row9 only
        'FINAL TAX' => 'vat_final_tax',           // L: row9 only
        'CHECKING' => 'wtx_checking',              // M: row9 only — formula cell, calculated value only
        'WTX TOTAL' => 'wtx_total',                // N: row8='WTX' + row9='Total' — formula cell
        'LOCAL TAX' => 'local_tax',                // O: row9 only
        'REFUND OF OVERPAYMENT LIQUIDATED DAMAGES' => 'refund_liquidated_damages', // P: row8+row9 combined, leading slash stripped by normalize()
        'RETENTION' => 'retention',                 // Q: row8 only
        'TOTAL DEDUCTIONS' => 'total_deductions',   // R: row8 only — formula cell
    ],

    // W-status text -> status_legend.status_code
    // Falls back to color matching only when text is genuinely blank —
    // per client confirmation, text is normally always present.
    'status_text_map' => [
        'I' => 'issued',
        'CA' => 'cash_advance',
        'CURRENT YEAR DD' => 'current_year_dd',
        'CURRENT NYDD' => 'current_nydd',
        'PY NY BECAME DD ON CURRENT YEAR' => 'py_ny_became_dd_current_year',
        'RE-ISSUANCE' => 're_issuance',
        'CANCELLED' => 'cancelled',
        'UNISSUED BY CASHIER' => 'unissued_by_cashier',
    ],

    // Payment mode (A/C column) — confirmed always present on real rows;
    // a blank value means the row is an NTA/release-of-funds notation,
    // not a real transaction, and must be skipped entirely.
    'payment_mode_map' => [
        'C' => 'check',
        'A' => 'ada',
    ],
];