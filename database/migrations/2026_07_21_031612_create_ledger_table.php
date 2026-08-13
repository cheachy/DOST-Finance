<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talaan - General Ledger (MDS 101) storage.
 *
 * MODEL
 *  - The general ledger is the single source of truth. Subsidiary Ledgers,
 *    their summaries, and the monthly RODs are NOT stored: they are derived by
 *    filtering these rows on charging_code and aggregating. Balances and totals
 *    are always computed at query time.
 *
 *  - SNAPSHOT ON IMPORT (not append). The accountant maintains one workbook and
 *    re-imports it as months are appended. Each import is a full snapshot tied
 *    to an upload; the newest completed upload is the live ledger
 *    (uploads.is_current). Re-importing the same growing file therefore cannot
 *    duplicate earlier months, and a payment mode that was blank in an earlier
 *    import simply appears filled in the newer snapshot.
 *
 *  - The deduction/tax block is stored as jsonb, not typed columns. Philippine
 *    tax rules change between years, so the number and names of those columns
 *    are not stable. Typed columns are reserved for the stable, aggregated
 *    fields (gross, net, charging_breakdown, payment_mode).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- file uploads: one row per import, newest completed = live -----
        Schema::create('uploads', function (Blueprint $t) {
            $t->id();
            $t->string('original_name');
            $t->string('stored_path');
            $t->string('sheet_name')->default('MDS 101');
            $t->string('fund_cluster')->nullable();       // e.g. "Fund Cluster- 01"
            $t->smallInteger('fiscal_year')->nullable();  // e.g. 2026
            $t->string('status')->default('pending');     // pending|parsing|done|failed
            $t->boolean('is_current')->default(false);    // the live snapshot

            // duplicate guard. file_hash fingerprints the bytes (catches a
            // literal re-upload); content_hash fingerprints the parsed rows
            // (catches a file re-saved by Excel whose data is unchanged).
            $t->char('file_hash', 64)->nullable();
            $t->char('content_hash', 64)->nullable();

            $t->integer('row_count')->nullable();
            $t->integer('transaction_count')->nullable();

            // retention bookkeeping. The uploads row is permanent (audit trail);
            // its heavy artefacts are pruned independently.
            $t->timestampTz('rows_pruned_at')->nullable();
            $t->timestampTz('file_deleted_at')->nullable();
            $t->jsonb('header_map')->default('{}');       // discovered columns, for audit
            $t->text('failure_reason')->nullable();
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();

            $t->index(['fiscal_year', 'is_current']);
        });

        // ---- lookup codes for CHARGING and RC (seeded from the Ref sheet) ---
        Schema::create('sl_tabs', function (Blueprint $t) {
            // The registry of subsidiary-ledger tabs. Adding a 4th tab (e.g.
            // ONELAB) is a DATA operation from here on - insert a row, no code
            // change, no deploy. See App\Console\Commands\SlTabCommand.
            $t->string('code')->primary();      // 'PS', 'MOOE', 'GIA', ...
            $t->string('label');                // display name
            $t->unsignedSmallInteger('display_order')->default(0);
            $t->boolean('is_active')->default(true);
        });

        DB::table('sl_tabs')->insert([
            ['code' => 'PS',   'label' => 'PS',   'display_order' => 1, 'is_active' => true],
            ['code' => 'MOOE', 'label' => 'MOOE', 'display_order' => 2, 'is_active' => true],
            ['code' => 'GIA',  'label' => 'GIA',  'display_order' => 3, 'is_active' => true],
        ]);

        Schema::create('account_references', function (Blueprint $t) {
            $t->id();
            $t->string('ref_type');                    // 'charging' | 'rc'
            $t->string('code');                        // 'Regular MOOE', '01-01 FOD'
            $t->string('label')->nullable();
            $t->string('allotment_class')->nullable(); // PS | MOOE | CO  (drives the ROD block)
            $t->string('sl_tab')->nullable();          // which SL tab this routes to
            $t->boolean('is_prior_year')->default(false); // current vs prior year allotment
            $t->boolean('is_active')->default(true);
            // When set, this row is an ALIAS: a typo'd/inconsistent charging-
            // code string actually seen in the ledger (e.g. "Regular-Onelab")
            // that means the SAME fund as the canonical code named here (e.g.
            // "Regular MOOE (Onelab)"). Its allotment_class/sl_tab/is_active/
            // is_prior_year are always refreshed to MATCH the canonical row -
            // aliases never carry an independent scope decision. Managed via
            // `php artisan ledger:alias`, never a hardcoded PHP list.
            $t->string('canonical_code')->nullable();
            $t->unique(['ref_type', 'code']);
        });

        // ---- the general ledger itself --------------------------------------
        Schema::create('general_ledgers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $t->integer('source_row');                 // original Excel row (provenance)
            $t->string('row_type')->default('transaction');
            // transaction | allotment_header | month_divider | section_header | subtotal | unknown

            $t->smallInteger('ledger_year');
            $t->smallInteger('ledger_month')->nullable(); // 1..12, forward-filled from divider

            // identity
            $t->string('obr_prefix')->nullable();
            $t->string('obr_no')->nullable();
            $t->string('payee')->nullable();
            $t->string('charging_code')->nullable();   // routing key for SL generation
            $t->string('rc_code')->nullable();
            $t->text('particulars')->nullable();
            $t->string('status', 8)->nullable();       // the 'W' column: I|NY|DD|CA

            // The row's legend fill colour, resolved to its label. Carries status
            // the 'W' column does not: rows marked CANCELLED whose W says I/NY,
            // and UNISSUED BY CASHIER which has no W code at all. ~639 rows in
            // the CY2026 workbook. Informational - A/C alone gates the ROD.
            $t->string('status_flag')->nullable();
            $t->jsonb('rod_actual')->nullable(); // her real AR-AW values: {cur_ps,cur_mooe,cur_co,prior_ps,prior_mooe,prior_co}

            // money (stable, typed - these drive every aggregation)
            $t->decimal('charging_breakdown', 15, 2)->nullable();
            $t->decimal('gross_amount', 15, 2)->nullable();
            $t->decimal('net_amount', 15, 2)->nullable();
            $t->decimal('receipts', 15, 2)->nullable(); // NCA/allotment on header rows

            // disbursement meta
            $t->date('payment_date')->nullable();
            $t->string('dv_prefix')->nullable();
            $t->string('dv_no')->nullable();
            $t->string('acct_code')->nullable();       // UACS object code
            $t->string('jev_no')->nullable();
            $t->text('remarks')->nullable();
            $t->char('payment_mode', 1)->nullable();   // A (ADA) | C (Check) - gates the ROD

            // volatile / provenance
            $t->jsonb('tax_details')->default('{}');   // deduction block, keyed by sheet label
            $t->jsonb('extras')->default('{}');        // numeric-RC, label overflow, etc.
            $t->jsonb('raw_row')->default('{}');       // full original row
            $t->timestampTz('created_at')->useCurrent();

            // hot path: filter a month down to one SL tab
            $t->index(['upload_id', 'ledger_year', 'ledger_month', 'charging_code'], 'gl_snapshot_month_charging_idx');
            $t->index(['upload_id', 'source_row']);
            $t->index('payment_date');
        });

        // Defence in depth: the service checks for duplicates before parsing,
        // but these make a duplicate *completed* import impossible at the DB
        // level. Failed/rejected attempts are unaffected, so a retry after a
        // genuine failure still works.
        DB::statement("CREATE UNIQUE INDEX uploads_file_hash_unique ON uploads (fiscal_year, file_hash) WHERE status = 'done' AND file_hash IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX uploads_content_hash_unique ON uploads (fiscal_year, content_hash) WHERE status = 'done' AND content_hash IS NOT NULL");

        // partial + GIN indexes need raw SQL
        DB::statement("CREATE INDEX gl_tx_idx ON general_ledgers (upload_id, charging_code) WHERE row_type = 'transaction'");
        DB::statement('CREATE INDEX gl_paid_idx ON general_ledgers (upload_id, payment_mode) WHERE payment_mode IS NOT NULL');
        DB::statement('CREATE INDEX gl_tax_gin ON general_ledgers USING GIN (tax_details)');
        DB::statement('CREATE INDEX gl_extras_gin ON general_ledgers USING GIN (extras)');
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
        Schema::dropIfExists('account_references');
        Schema::dropIfExists('sl_tabs');
        Schema::dropIfExists('uploads');
    }
};
