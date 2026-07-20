<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fund_receipts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->integer('fiscal_year')->nullable();
            $table->string('source')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->foreignId('import_id')->nullable()->constrained('excel_imports')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('fiscal_year', 'idx_fund_receipts_fiscal_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_receipts');
    }
};
