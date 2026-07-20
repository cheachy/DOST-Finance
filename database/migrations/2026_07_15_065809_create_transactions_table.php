<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('import_id')->constrained('excel_imports')->nullOnDelete();
            $table->foreignId('payee_id')->constrained('payees');
            $table->foreignId('user_id')->constrained('users');

            $table->date('date')->nullable();
            $table->text('particulars')->nullable();
            $table->string('rc', 50)->nullable();
            $table->string('w')->nullable();

            $table->integer('fiscal_year')->nullable();

            $table->string('obr_month', 20)->nullable();
            $table->string('obr_sequence', 50)->nullable();
            $table->string('dv_month', 20)->nullable();
            $table->string('dv_sequence', 50)->nullable();
            $table->string('jev_no', 50)->nullable();

            $table->string('deduction_type', 20)->nullable();   // 'rlip' | NULL | future values
            $table->string('payment_mode', 10)->nullable();     // 'check' | 'ada' the per-row A/C flag (C=Check, A=ADA/Advice to Debit Account)

            $table->decimal('receipts', 15, 2)->nullable();
            $table->decimal('charging_breakdown', 15, 2)->nullable(); // feeds WTX/deduction calculations
            
            $table->decimal('gross', 15, 2)->nullable();
            $table->decimal('net', 15, 2)->nullable();
            $table->decimal('payment_net_wtx', 15, 2)->nullable();

            $table->decimal('paid_nydd_prior', 15, 2)->nullable();
            $table->decimal('paid_aps_prior', 15, 2)->nullable();
            $table->decimal('paid_nydd_curr', 15, 2)->nullable();
            $table->decimal('paid_aps_curr', 15, 2)->nullable();


            $table->timestamps();

            $table->foreign('w')->references('status_code')->on('status_legend');

            $table->index(['account_id', 'fiscal_year', 'dv_month'], 'idx_transaction_account_month');
            $table->index('import_id', 'idx_transactions_import');
            $table->index(['account_id', 'date', 'dv_sequence', 'obr_sequence', 'particulars'], 'idx_transactions_dedup');
        });

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT chk_transactions_payment_mode CHECK (payment_mode IN ('check', 'ada'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
