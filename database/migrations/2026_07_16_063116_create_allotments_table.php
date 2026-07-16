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
        Schema::create('allotments', function (Blueprint $table) {
            $table->id();
            $table->integer('fiscal_year');
            $table->string('category', 10); // 'PS' | 'MOOE' | 'CO'
            $table->string('alloment_year_type', 10);   // 'current' | 'prior'
            $table->string('allotment_class')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['fiscal_year', 'category'], 'idx_allotments_fiscal');
        });

        DB::statement("ALTER TABLE allotments ADD CONSTRAINT chk_allotments_category CHECK (category IN ('PS', 'MOOE', 'CO'))");
        DB::statement("ALTER TABLE allotments ADD CONSTRAINT chk_allotments_type CHECK (category IN ('current', 'prior'))");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allotments');
    }
};
