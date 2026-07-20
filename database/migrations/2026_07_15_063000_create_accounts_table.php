<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable();
            $table->string('charging_value')->unique(); // raw dropdown text, e.g. 'Regular MOOE (Onelab)'
            $table->string('category', 10)->nullable(); // 'PS' | 'MOOE' | 'CO' | null (not all charging values map to one)
            $table->string('fund_status', 30)->nullable(); // 'regular' | 'continuing' | 'prior_year_payables' | named program; null pending review for new SLs
            $table->string('sub_project')->nullable();     // e.g. 'Onelab', 'Emieerald'; null for plain Regular/CO/etc.
            $table->timestamps();
        });

        // Postgres check constraint — Laravel's schema builder has no
        // first-class helper for this, so it's added via raw SQL.
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT chk_accounts_category CHECK (category IN ('PS', 'MOOE', 'CO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};