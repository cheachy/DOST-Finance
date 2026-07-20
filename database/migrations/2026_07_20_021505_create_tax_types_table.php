<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique(); // e.g. 'vat_comp', 'vat_evat' — normalized from header text
            $table->string('label');                // display text, e.g. 'Comp', 'EVAT'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_types');
    }
};