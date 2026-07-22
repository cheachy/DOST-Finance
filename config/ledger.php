<?php

/**
 * The ledger parsing configuration.
 *
 * The header dictionary lives here as DATA. 
 * 
 * Column positions are discovered at import time by matching each column's header labels
 *
 * The deduction/tax block is NOT listed. It can be discovered by
 * BOUNDARY ANCHORS and swept dynamically, because Philippine tax rules change
 * between years and the columns in that block are not stable. Whatever columns
 * exist between the anchors are captured into the tax_details jsonb, keyed by
 * the label the sheet itself uses.
 *
 * All keys are already normalized.
 */
return [
    'sheet'           => 'MDS 101',
    'header_scan_max' => 40,   // how many top rows to scan for the header band
    'blank_run_limit' => 80,   // stop after this many consecutive blank rows

    // every one of these labels must appear on a row for it to be the header row
    'anchors' => ['obr #', 'payee', 'charging', 'rc', 'particulars'],

    'months' => [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
    ],

    // to skip (POSTING = processing dates/times)
    'ignore_super' => ['posting'],

    'header' => [
        // matched on the MAIN header label -> canonical field
        'by_main' => [
            'obr #'              => 'obr_prefix',
            'payee'              => 'payee',
            'charging'           => 'charging',
            'rc'                 => 'rc',
            'particulars'        => 'particulars',
            'charging breakdown' => 'charging_breakdown',
            'gross'              => 'gross',
            'net'                => 'net',
            'receipts'           => 'receipts',
            'acct codes'         => 'acct_code',
            'jev #'              => 'jev_no',
            'remarks'            => 'remarks',
            'date'               => 'pay_date',      // POSTING dates ignored above
            'rod'                => 'payment_mode',  // ROD A/C (ROD Block)
        ],

        
        'status_sub_label'    => 'w',
        'status_allowed_main' => ['', 'vat', 'wtx'],

        // split columns
        'dv_main'        => 'dv#',        // two merged cols -> dv_prefix, dv_no
        'obr_split_from' => 'obr_prefix', // obr_no = the column right after OBR #
    ],

    /*
     | Deduction / tax block (dynamic).
     |
     | Starts at the column immediately after 'start_after', ends at the column
     | whose main label begins with any of 'end_labels'. Every column in between
     | becomes a tax_details key, except 'skip_labels' (the status flag and the
     | sheet's own computed columns).
     |
     | Verified against the CY2026 workbook, which yields:
     |   comp, evat, vt, pt, final tax, local tax,
     |   refund of overpayment/liquidated damages, retention
     |
     | A tax-law change that adds, renames, or removes a column here needs no
     | code change and no migration.
     */
    'tax_block' => [
        'start_after' => 'particulars',
        'end_labels'  => ['total deductions'],
        'skip_labels' => ['w', 'checking', 'total'],
        // a sub label beginning with "/" continues its main label
        'join_continuation' => true,
        // group labels that should never become a key on their own
        'group_labels' => ['vat', 'wtx'],
    ],

    // canonical fields extracted per row, in order (remarks + payment_mode last)
    'fields' => [
        'obr_prefix', 'obr_no', 'payee', 'charging', 'rc', 'particulars', 'status',
        'charging_breakdown', 'gross', 'net', 'pay_date', 'dv_prefix', 'dv_no',
        'receipts', 'acct_code', 'jev_no', 'remarks', 'payment_mode',
    ],

    // canonical parser field -> general_ledgers column (unlisted map 1:1)
    'column_map' => [
        'charging' => 'charging_code',
        'rc'       => 'rc_code',
        'gross'    => 'gross_amount',
        'net'      => 'net_amount',
        'pay_date' => 'payment_date',
    ],

    // fields holding Excel date serials, converted to Y-m-d
    'date_fields' => ['pay_date'],

    // row-classification markers (matched against payee + particulars + rod)
    'markers' => [
        'subtotal' => ['TOTAL', 'PS TAX', 'PS ACCTG', 'PS BUDGET', 'ACCOUNTING', 'BUDGET'],
        'section'  => ['OBLIGATION', 'BECAME DD', 'BECOME DD', 'PRIOR MONTHS', 'NYDD', 'CANCELLED', 'REVERSION'],
    ],

    // snapshot retention: keep this many past snapshots for audit, 0 = keep all
    'keep_snapshots' => 5,
];