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
        Schema::create('status_legend', function (Blueprint $table) {
            $table->id();
            $table->string('status_code', 50)->unique();        // 'current_year_dd'
            $table->string('label', 255);                       // 'CURRENT YEAR DD'
            $table->string('fill_color_hex', 7)->nullable();    // NULL = 'I' status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_legends');
    }
};
