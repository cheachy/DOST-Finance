<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the state of the (testing-only) ROD write-back export.
 *
 * Generating it is CPU/memory heavy on the real workbook - confirmed
 * 2026-08-13 against the production FY2026 file (2,142 transactions, 10 MB):
 * ~288s and ~1.9 GB peak just to load + write it back with PhpSpreadsheet.
 * That does not belong in a web request (see App\Jobs\RodExportJob, and the
 * same reasoning already applied to imports in App\Jobs\ImportLedgerJob), so
 * it runs on the queue and this is how the UI polls for the result instead
 * of holding a request open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $t) {
            $t->string('rod_export_status')->nullable()->after('header_map'); // queued|done|failed
            $t->string('rod_export_path')->nullable()->after('rod_export_status');
            $t->timestampTz('rod_exported_at')->nullable()->after('rod_export_path');
            $t->text('rod_export_error')->nullable()->after('rod_exported_at');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $t) {
            $t->dropColumn(['rod_export_status', 'rod_export_path', 'rod_exported_at', 'rod_export_error']);
        });
    }
};
