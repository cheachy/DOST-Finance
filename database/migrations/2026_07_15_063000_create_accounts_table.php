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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable();
            $table->string('charging_value')->unique(); // dropdown text
            $table->string('category', 10)->nullable();  // 'PS' | 'MODE' | 'CO'
            $table->string('fund_status', 30); // 'regular', 'continuing', 'prior_year_payables'
            $table->string('sub_project')->nullable(); // e.g 'Onelab', 'Emieerald'
            $table->timestamps();
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT chk_accounts_category CHECK (category IN ('PS', 'MOOE', 'CO'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
