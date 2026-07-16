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
        Schema::create('sheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_name', 255)->unique();                // Excel tab name (SL)
            $table->string('template_file_path', 500)->nullable();
            $table->jsonb('column_map')->default('{}');
            $table->integer('first_data_row_number')->nullable();
            $table->integer('last_data_row_number')->nullable();
            $table->integer('default_fiscal_year')->nullable();         // For achived tab like 'ONELAB 2025'
            $table->boolean('is_master_template')->default('false');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_templates');
    }
};
