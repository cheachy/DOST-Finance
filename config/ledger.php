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
    'sheet' => 'MDS 101',
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
            'obr #' => 'obr_prefix',
            'payee' => 'payee',
            'charging' => 'charging',
            'rc' => 'rc',
            'particulars' => 'particulars',
            'charging breakdown' => 'charging_breakdown',
            'gross' => 'gross',
            'net' => 'net',
            'receipts' => 'receipts',
            'acct codes' => 'acct_code',
            'jev #' => 'jev_no',
            'remarks' => 'remarks',
            'date' => 'pay_date',      // POSTING dates ignored above
            'rod' => 'payment_mode',  // ROD A/C - gates the ROD block
        ],

        // the only fixed field inside the deduction block is the status flag
        'status_sub_label' => 'w',
        'status_allowed_main' => ['', 'vat', 'wtx'],

        // split columns
        'dv_main' => 'dv#',        // two merged cols -> dv_prefix, dv_no
        'obr_split_from' => 'obr_prefix', // obr_no = the column right after OBR #

        // PS/MOOE/CO appear TWICE in the header (once under CURRENT YEAR ALLOTMENT,
        // once under PRIOR YEAR ALLOTMENT) - disambiguated by the super-label, checked
        // in canon() BEFORE by_main, since 'ps'/'mooe'/'co' alone are ambiguous.
        'rod_super' => [
            'current year allotment' => 'cur',
            'prior year allotment'   => 'prior',
        ],
        'rod_classes' => ['ps', 'mooe', 'co'],
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
        'end_labels' => ['total deductions'],
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

    /*
     | Classification & routing rules — CONFIRMED by the accountant (interview).
     |
     | Key correction to earlier assumptions: the ACCOUNT CODE is ignored
     | entirely (it is nullable and not the basis). Classification is the
     | CHARGING CODE, full stop.
     */
    'classify' => [
        // current-year class from the charging code
        'ps_codes' => ['regular ps'],      // exact (normalized) match -> PS
        'co_contains' => 'co',                // charging containing 'co' -> CO
        // everything else current-year -> MOOE

        // prior-year gate: these charging codes mean prior-year allotment
        'prior_year_codes' => ['prior year payables', 'prior years payable', '2025 nydd'],

        /*
         | Second prior-year gate, which OVERRIDES the charging code.
         |
         | A prior-year not-yet-due obligation that became due-and-demandable
         | this year keeps its original current-year charging code (e.g.
         | "SAA-SARAI") but is paid from the PRIOR-year allotment. Nothing in
         | the typed columns says so - the sheet records it only as a legend
         | fill colour, so the flag has to win over the code.
         |
         | Verified 2026-08-13: 93 disbursed rows carry this flag, and every
         | one was typed into her prior-year ROD columns (8,356,033.81 total),
         | none into current-year. Without this gate, May MOOE over-counted by
         | 3,952,494.51. See GeneralLedger::scopeCurrentYearAllotment().
         */
        'prior_year_flags' => ['PY NY BECAME DD ON CURRENT YEAR'],

        // Prior-year PS/MOOE/CO is NOT auto-derived. The accountant confirmed
        // there is no rule; she annotates the particulars manually. So prior-year
        // rows route to the prior-year bucket WITHOUT a class split - flag them
        // for her rather than guessing.
        'prior_year_class' => 'manual',
    ],

    /*
     | Remittance detection.
     | A numeric value in the RC column is the reliable signal (RC should hold a
     | code, not a number). The "remit"/"rem"/"to remit" keyword only appears on
     | ~63% of these rows, so it is a secondary confirmation, not the test.
     | The numeric RC value IS the transaction amount and flows to the ROD.
     */
    'remittance' => [
        'detect_on_numeric_rc' => true,
        'keyword_hints' => ['to remit', 'remit', 'rem of', 'rem '],
    ],

    /*
     | Status notes written into the RC column (they overwrite whatever code was
     | there). "PD ON 3/26" = paid-on stamp added AFTER the fact to the original
     | not-yet-due row, recording it was settled on that date; the actual payment
     | is a separate row in the paid month carrying the A/C. "cancelled by BO ..."
     | = cancelled BEFORE payment (these carry no A/C). These are NOT
     | responsibility centres and NOT GIA programs, and because they are stamped
     | onto the original row they destroy the RC/program that was there.
     |
     | NOTE two distinct cancellations exist:
     |   - cancelled BEFORE payment: "cancelled by BO" text in RC, no A/C -> never
     |     hits the ROD (gated out).
     |   - cancelled AFTER payment: CANCELLED fill colour, still has A/C and a ROD
     |     amount (e.g. a check issued then voided). The same OBR may reappear in a
     |     later month and be re-issued (particulars prefixed "Reissuance-").
     |     ROD treatment of these is an OPEN question - see ACCOUNTANT_QUESTIONS_ROUND2 B7.
     */
    'reissuance_marker' => 'reissuance',   // matched at the start of particulars
    'rc_status_notes' => [
        'paid' => '/^\s*(pd|ap)\s*(on)?\s*\d/i',   // PD ON 3/26, AP ON 5/26, PD 7/26
        'cancelled' => '/cancelled/i',
    ],

    /*
     | GIA program split (CEST / LGIA / SSCP).
     | The program is the FIRST token of the RC value (after an optional 'GIA-'
     | prefix). Compute all three DIRECTLY and compare to the total - do NOT
     | derive CEST by subtraction (the workbook does, which silently absorbs
     | unclassified rows into CEST).
     | ~140 GIA rows have their RC overwritten by a status note (see above), so
     | their program survives only on the repeated paid-month row - flag any that
     | cannot be resolved.
     */
    'gia_programs' => ['CEST', 'LGIA', 'SSCP'],

    /*
     | Charging-code normalisation. "Regular MOOE (Emieerald)" and
     | "Regular-EMIEERALD" are the same fund; the parenthesised name is the
     | project = the SL tab. Manually-typed variants (no dropdown) are typos to
     | fold together. Confirmed with the accountant; seed real aliases from Ref.
     */
    'charging_aliases' => [
        // canonical => [variants...]  (extend from the Ref sheet)
        'Regular MOOE (Onelab)' => ['regular-onelab', 'regular mooe (onelab)'],
        'Regular MOOE (Emieerald)' => ['regular-emieerald', 'regular mooe (emieerald)'],
        'Prior Year Payables' => ['prior years payable', 'prior year payables'],
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
        'rc' => 'rc_code',
        'gross' => 'gross_amount',
        'net' => 'net_amount',
        'pay_date' => 'payment_date',
    ],

    // fields holding Excel date serials, converted to Y-m-d
    'date_fields' => ['pay_date'],

    // row-classification markers (matched against payee + particulars + rod)
    /*
     | Markers used by classify() as substring matches against the PAYEE cell
     | only (not particulars - see LedgerRowParser::classify() for why).
     |
     | 'ACCOUNTING' was briefly removed 2026-07-29 after it mis-matched real
     | transactions whose PARTICULARS mentioned the accounting office. The
     | root cause was that marker matching scanned particulars at all; once
     | that was scoped to payee only, 'ACCOUNTING' is safe again and is
     | restored here - several genuine header rows have payee = "ACCOUNTING"
     | exactly (e.g. source_row 158, 495, 968, 1444, 1866, 2319).
     |
     | 'BUDGET' and 'TOTAL' had the same particulars false-positive problem
     | (source_row 343 "...budget proposal review...", 954/1601/1960 "...total
     | organic carbon testing..." - a lab-test name). Same fix applies: safe
     | now that only payee is scanned.
     */
    'markers' => [
        'subtotal' => ['TOTAL', 'PS TAX', 'PS ACCTG', 'PS BUDGET', 'ACCOUNTING', 'BUDGET'],
        'section' => ['OBLIGATION', 'BECAME DD', 'BECOME DD', 'PRIOR MONTHS', 'NYDD', 'CANCELLED', 'REVERSION'],
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
    'keep_files' => 1,   // workbook files kept on disk (0 = delete after parse)

    /*
     | raw_row stores the full original row per record. It roughly triples the
     | stored payload (~1.1 MB vs ~0.5 MB per snapshot) and duplicates data
     | already held in typed columns + tax_details. Enable only while debugging
     | a new workbook layout.
     */
    'store_raw_row' => false,
];
