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
        Schema::create('transaction_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();

            $table->decimal('vat_comp', 15, 2)->nullable();
            $table->decimal('vat_vt', 15, 2)->nullable();
            $table->decimal('vat_pt', 15, 2)->nullable();
            $table->decimal('vat_final_tax', 15, 2)->nullable();
            $table->decimal('wtx_checking', 15, 2)->nullable();
            $table->decimal('local_tax', 15, 2)->nullable();

            $table->decimal('refund_liquidated_damages', 15, 2)->nullable();
            $table->decimal('retention', 15, 2)->nullable();
            $table->decimal('total_deduction', 15, 2)->nullable();
            $table->decimal('due_to_officers', 15, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_taxes');
    }
};
