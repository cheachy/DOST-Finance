<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talaan - General Ledger (MDS 101) storage.
 *
 * The general ledger is the single source of truth. Subsidiary Ledgers and their
 * ROD summaries are NOT stored here - they are derived by filtering these rows on
 * charging_code and aggregating. Balances, totals, and ROD summaries are always
 * computed at query time, never persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- file uploads: versioning + import status -----------------------
        Schema::create('uploads', function (Blueprint $t) {
            $t->id();
            $t->string('original_name');
            $t->string('stored_path');
            $t->string('sheet_name')->default('MDS 101');
            $t->string('fund_cluster')->nullable();     // e.g. "Fund Cluster- 01"
            $t->smallInteger('fiscal_year')->nullable(); // e.g. 2026
            $t->string('status')->default('pending');    // pending|parsing|done|failed
            $t->integer('row_count')->nullable();
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->timestamps();
        });

        // ---- lookup codes for CHARGING and RC (seeded from the Ref sheet) ----
        Schema::create('account_references', function (Blueprint $t) {
            $t->id();
            $t->string('ref_type');                 // 'charging' | 'rc'
            $t->string('code');                     // 'Regular MOOE', '01-01 FOD', ...
            $t->string('label')->nullable();
            $t->string('allotment_class')->nullable(); // PS | MOOE | CO  (charging rows)
            $t->string('sl_tab')->nullable();          // which SL tab this routes to
            $t->boolean('is_active')->default(true);
            $t->unique(['ref_type', 'code']);
        });

        // ---- the general ledger itself --------------------------------------
        Schema::create('general_ledgers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $t->integer('source_row');              // original Excel row (provenance)
            $t->string('row_type')->default('transaction');
            // row_type: transaction | allotment_header | month_divider | section_header | subtotal | unknown

            $t->smallInteger('ledger_year');
            $t->smallInteger('ledger_month');       // 1..12, forward-filled from divider

            // identity
            $t->string('obr_prefix')->nullable();
            $t->string('obr_no')->nullable();
            $t->string('payee')->nullable();
            $t->string('charging_code')->nullable(); // routing key for SL generation
            $t->string('rc_code')->nullable();
            $t->text('particulars')->nullable();
            $t->string('status', 8)->nullable();     // the 'W' column: I|NY|DD|CA

            // raw tax / deduction inputs (stored; totals are computed)
            $t->decimal('tax_comp', 15, 2)->nullable();
            $t->decimal('tax_evat', 15, 2)->nullable();
            $t->decimal('tax_vt', 15, 2)->nullable();
            $t->decimal('tax_pt', 15, 2)->nullable();
            $t->decimal('tax_final', 15, 2)->nullable();
            $t->decimal('local_tax', 15, 2)->nullable();
            $t->decimal('refund_liquidated', 15, 2)->nullable();
            $t->decimal('retention', 15, 2)->nullable();

            // money (source values)
            $t->decimal('charging_breakdown', 15, 2)->nullable();
            $t->decimal('gross_amount', 15, 2)->nullable();
            $t->decimal('net_amount', 15, 2)->nullable();
            $t->decimal('receipts', 15, 2)->nullable(); // NCA/allotment on header rows

            // disbursement meta
            $t->date('payment_date')->nullable();
            $t->string('dv_prefix')->nullable();
            $t->string('dv_no')->nullable();
            $t->string('acct_code')->nullable();     // UACS object code(s)
            $t->string('jev_no')->nullable();
            $t->text('remarks')->nullable();
            $t->char('payment_mode', 1)->nullable(); // A (ADA) | C (Check)

            // catch-alls
            $t->jsonb('extras')->default('{}');      // numeric-RC, label overflow, etc.
            $t->jsonb('raw_row')->default('{}');     // full original row for re-derivation
            $t->timestampTz('created_at')->useCurrent();

            // hot path: filter a month down to one SL tab
            $t->index(['ledger_year', 'ledger_month', 'rc_code', 'charging_code'], 'gl_month_rc_charging_idx');
            $t->index(['upload_id', 'source_row']);
            $t->index('payment_date');
        });

        // partial + GIN indexes need raw SQL
        DB::statement("CREATE INDEX gl_status_tx_idx ON general_ledgers (status) WHERE row_type = 'transaction'");
        DB::statement("CREATE INDEX gl_extras_gin ON general_ledgers USING GIN (extras)");
        DB::statement("CREATE INDEX gl_raw_row_gin ON general_ledgers USING GIN (raw_row)");
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
        Schema::dropIfExists('account_references');
        Schema::dropIfExists('uploads');
    }
};