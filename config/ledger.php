<?php

/**
 * Talaan ledger parsing configuration.
 *
 * The header dictionary lives here as DATA, not code: column positions are
 * discovered at import time by matching each column's (super, main, sub) header
 * labels against the maps below. Move a column, rename a header, or add a month
 * and the importer adapts without a code change.
 *
 * All keys are "normalized": lower-cased, trimmed, internal whitespace collapsed
 * to single spaces. LedgerImportService::norm() produces the same form.
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

    // whole super-group blocks to skip entirely (POSTING = processing dates/times,
    // batch/individual processing). Nothing under these is parsed.
    'ignore_super' => ['posting'],

    'header' => [
        // matched on the MAIN header label -> canonical field
        'by_main' => [
            'obr #'                 => 'obr_prefix',
            'payee'                 => 'payee',
            'charging'              => 'charging',
            'rc'                    => 'rc',
            'particulars'           => 'particulars',
            'refund of overpayment' => 'refund_liquidated',
            'retention'             => 'retention',
            'charging breakdown'    => 'charging_breakdown',
            'gross'                 => 'gross',
            'net'                   => 'net',
            'receipts'              => 'receipts',
            'acct codes'            => 'acct_code',
            'jev #'                 => 'jev_no',
            'remarks'               => 'remarks',
            'date'                  => 'pay_date',      // POSTING dates are ignored above
            'rod'                   => 'payment_mode',  // ROD A/C
        ],

        // matched on the SUB header when the main cell is blank or a group label
        'by_sub' => [
            'w'         => 'status',
            'comp'      => 'tax_comp',
            'evat'      => 'tax_evat',
            'vt'        => 'tax_vt',
            'pt'        => 'tax_pt',
            'final tax' => 'tax_final',
            'local tax' => 'local_tax',
        ],
        // by_sub only applies when the main label is one of these
        'by_sub_allowed_main' => ['', 'vat', 'wtx'],

        // split columns
        'dv_main'        => 'dv#',        // two merged cols -> dv_prefix, dv_no
        'obr_split_from' => 'obr_prefix', // obr_no = the column immediately after OBR #
    ],

    // row-classification markers (matched case-insensitively against
    // payee + particulars + payment_mode)
    'markers' => [
        'subtotal' => ['TOTAL', 'PS TAX', 'PS ACCTG', 'PS BUDGET', 'ACCOUNTING', 'BUDGET'],
        'section'  => ['OBLIGATION', 'BECAME DD', 'BECOME DD', 'PRIOR MONTHS', 'NYDD', 'CANCELLED', 'REVERSION'],
    ],

    // canonical fields extracted per row, in order (remarks + payment_mode last)
    'fields' => [
        'obr_prefix', 'obr_no', 'payee', 'charging', 'rc', 'particulars', 'status',
        'tax_comp', 'tax_evat', 'tax_vt', 'tax_pt', 'tax_final',
        'local_tax', 'refund_liquidated', 'retention', 'charging_breakdown',
        'gross', 'net', 'pay_date', 'dv_prefix', 'dv_no', 'receipts',
        'acct_code', 'jev_no', 'remarks', 'payment_mode',
    ],

    // canonical parser field -> general_ledgers column (unlisted fields map 1:1)
    'column_map' => [
        'charging' => 'charging_code',
        'rc'       => 'rc_code',
        'gross'    => 'gross_amount',
        'net'      => 'net_amount',
        'pay_date' => 'payment_date',
    ],

    // fields that hold Excel date serials and must be converted to Y-m-d
    'date_fields' => ['pay_date'],
];