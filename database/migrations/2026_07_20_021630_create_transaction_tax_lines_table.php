<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('tax_type_id')->constrained('tax_types');
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'tax_type_id'], 'uq_transaction_tax_lines');
            $table->index('transaction_id', 'idx_transaction_tax_lines_transaction');
            $table->index('tax_type_id', 'idx_transaction_tax_lines_tax_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_tax_lines');
    }
};