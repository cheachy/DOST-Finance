<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_name')->unique(); // literal Excel tab name, e.g. 'ONELAB', 'ONELAB 2025'
            $table->string('template_file_path', 500)->nullable();
            $table->jsonb('column_map')->default('{}');
            $table->integer('header_row_number')->nullable();
            $table->integer('first_data_row_number')->nullable();
            $table->integer('last_data_row_number')->nullable();
            $table->integer('default_fiscal_year')->nullable(); // e.g. 2025 for an archived tab like 'ONELAB 2025'
            $table->boolean('is_master_template')->default(false); // cloned when a brand-new SL is detected
            $table->jsonb('unmapped_headers')->default('[]'); // header text detected but not in the dictionary — surfaced for review
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_templates');
    }
};