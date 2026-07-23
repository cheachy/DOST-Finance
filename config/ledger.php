<?php

/**
 * This is the ledger parsing configuration.
 *
 * The header dictionary lives here as DATA, not code: column positions are
 * discovered at import time by matching each column's header labels. Move a
 * column, rename a header, or append a month and the importer adapts.
 *
 * The deduction/tax block is NOT listed field-by-field. It is discovered by
 * BOUNDARY ANCHORS and swept dynamically, because Philippine tax rules change
 * between years and the columns in that block are not stable. Whatever columns
 * exist between the anchors are captured into the tax_details jsonb, keyed by
 * the label the sheet itself uses.
 *
 * All keys are "normalized": lower-cased, trimmed, whitespace collapsed.
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

    // whole super-group blocks to skip (POSTING = processing dates/times)
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
            'rod'                => 'payment_mode',  // ROD A/C - gates the ROD block
        ],

        // the only fixed field inside the deduction block is the status flag
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

    /*
     | Status legend (dynamic).
     |
     | The sheet carries a colour legend above the header: a filled swatch with
     | its label in the cell to the right. It is discovered as the longest
     | contiguous vertical run of (filled cell + text label) strictly above the
     | header row - which correctly finds column W and ignores the similarly
     | filled PROCESSING TIME block.
     |
     | Verified against CY2026, yielding 6 entries:
     |   CURRENT YEAR DD, CURRENT NYDD, PY NY BECAME DD ON CURRENT YEAR,
     |   RE-ISSUANCE, CANCELLED, UNISSUED BY CASHIER
     |
     | 639 data rows carry one of these fills. A new colour next year is picked
     | up automatically.
     */
    'legend' => [
        'min_entries' => 2,   // ignore runs shorter than this
        // fills treated as "no fill"
        'ignore_argb' => ['00000000', 'FFFFFFFF'],
        // columns probed to read a data row's fill, first match wins
        'probe_columns' => ['payee', 'charging', 'rc', 'particulars', 'obr_prefix'],
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

    /*
     | Retention.
     |
     | Three things accumulate at very different rates, so they are pruned
     | separately:
     |
     |   uploads rows   ~1 KB each   -> kept forever (the audit trail)
     |   ledger rows    ~2.8 MB/yr   -> kept for the newest N snapshots
     |   workbook files ~10 MB each  -> kept for the newest N only
     |
     | Pruning ledger rows does NOT delete the uploads row: the history of who
     | imported what and when survives at negligible cost, even once the row
     | data behind it is gone.
     |
     | The current workbook file is kept because export uses it as the template
     | that preserves the government format. Superseded files have no such use.
     */
    'keep_row_snapshots' => 2,   // snapshots that retain their general_ledgers rows
    'keep_files'         => 1,   // workbook files kept on disk (0 = delete after parse)

    /*
     | raw_row stores the full original row per record. It roughly triples the
     | stored payload (~1.1 MB vs ~0.5 MB per snapshot) and duplicates data
     | already held in typed columns + tax_details. Enable only while debugging
     | a new workbook layout.
     */
    'store_raw_row' => false,
];