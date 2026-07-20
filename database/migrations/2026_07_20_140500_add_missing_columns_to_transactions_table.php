<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add columns that the LedgerImportService writes but were
     * missing from the original transactions migration:
     * - acct_code: account code from the Excel (AG column)
     * - remarks: free-text remarks (AP column)
     * - due_to_officers: formula cell value — Due to Officers and Employees/AP (NET)
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('acct_code', 100)->nullable()->after('paid_aps_curr');
            $table->text('remarks')->nullable()->after('acct_code');
            $table->decimal('due_to_officers', 15, 2)->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['acct_code', 'remarks', 'due_to_officers']);
        });
    }
};
